<?php

/*
 * TransactionProcessor.php
 * Copyright (c) 2026 open-banking.io contribution to Firefly III (https://github.com/firefly-iii).
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\OpenBankingIo\Conversion\Routine;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Exceptions\RateLimitException;
use App\Models\ImportJob;
use App\Services\OpenBankingIo\Authentication\SecretManager;
use App\Services\OpenBankingIo\Request\GetTransactionsRequest;
use App\Services\OpenBankingIo\Response\GetTransactionsResponse;
use App\Services\Shared\Conversion\CreatesAccounts;
use App\Support\Internal\CollectsAccounts;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Class TransactionProcessor
 */
final class TransactionProcessor
{
    use CollectsAccounts;
    use CreatesAccounts;

    private ImportJob $importJob;
    private array $accounts;
    private ?Carbon $notAfter  = null;
    private ?Carbon $notBefore = null;

    /**
     * @throws ImporterErrorException
     */
    public function download(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $this->notBefore               = null;
        $this->notAfter                = null;
        $this->accounts                = [];
        $configuration                 = $this->importJob->getConfiguration();

        if ('' !== $configuration->getDateNotBefore()) {
            $this->notBefore = new Carbon($configuration->getDateNotBefore());
        }
        if ('' !== $configuration->getDateNotAfter()) {
            $this->notAfter = new Carbon($configuration->getDateNotAfter());
        }

        $accounts                      = $configuration->getAccounts();
        $return                        = [];
        Log::debug(sprintf('Found %d open-banking.io account(s) to download from.', count($accounts)));
        $this->existingServiceAccounts = $this->getOpenBankingIoAccounts($configuration);

        $parsed                        = SecretManager::parse(SecretManager::getCredentials($configuration));

        /**
         * @var string     $importServiceAccountId
         * @var int|string $fireflyIIIAccountId
         */
        foreach ($accounts as $importServiceAccountId => $fireflyIIIAccountId) {
            $importServiceAccountId          = (string) $importServiceAccountId;
            Log::debug(sprintf('[%s] Going to download open-banking.io transactions for account %s', config('importer.version'), $importServiceAccountId));

            if (0 === (int) $fireflyIIIAccountId) {
                $createdAccount                           = $this->createOrFindExistingAccount($importServiceAccountId);
                $updatedAccounts                          = $configuration->getAccounts();
                $updatedAccounts[$importServiceAccountId] = $createdAccount->id;
                $configuration->setAccounts($updatedAccounts);
                Log::debug(sprintf('Created Firefly III account #%d', $createdAccount->id));
            }

            $opts                            = [];
            if ($this->notBefore instanceof Carbon) {
                $opts['from'] = $this->notBefore->format('Y-m-d');
            }
            if ($this->notAfter instanceof Carbon) {
                $opts['to'] = $this->notAfter->format('Y-m-d');
            }

            $request                         = new GetTransactionsRequest(
                $parsed['apiKey'],
                $parsed['privateKey'],
                $parsed['apiBaseUrl'],
                $importServiceAccountId,
                $opts
            );
            $request->setTimeOut(config('importer.connection.timeout'));

            try {
                /** @var GetTransactionsResponse $transactions */
                $transactions = $request->get();
                Log::debug(sprintf('GetTransactionsResponse: count %d transaction(s)', count($transactions)));
            } catch (ImporterHttpException|RateLimitException $e) {
                Log::debug(sprintf('Ran into %s instead of GetTransactionsResponse', $e::class));
                $this->importJob->conversionStatus->addWarning(0, $e->getMessage());
                $return[$importServiceAccountId] = [];

                continue;
            }

            $return[$importServiceAccountId] = $this->filterTransactions($transactions);
        }
        Log::debug('Done with download of transactions.');

        return $return;
    }

    public function getAccounts(): array
    {
        return $this->accounts;
    }

    private function filterTransactions(GetTransactionsResponse $transactions): array
    {
        Log::info(sprintf('Going to filter downloaded transactions. Original set length is %d', count($transactions)));
        $return = [];
        foreach ($transactions as $transaction) {
            $madeOn   = $transaction->getDate();

            if ($this->notBefore instanceof Carbon && $madeOn->lt($this->notBefore)) {
                continue;
            }
            if ($this->notAfter instanceof Carbon && $madeOn->gt($this->notAfter)) {
                continue;
            }
            if (0 === bccomp('0', $transaction->amount)) {
                $this->importJob->conversionStatus->addWarning(0, sprintf(
                    'Transaction #%s ("%s") has an amount of zero and has been ignored.',
                    $transaction->id,
                    e($transaction->getDescription())
                ));

                continue;
            }

            $return[] = $transaction;
        }
        Log::info(sprintf('After filtering, set is %d transaction(s)', count($return)));

        return $return;
    }

    public function setImportJob(ImportJob $importJob): void
    {
        $this->importJob = $importJob;
        $this->importJob->refreshInstanceIdentifier();
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }
}
