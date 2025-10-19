<?php

/*
 * TransactionProcessor.php
 * Copyright (c) 2025 james@firefly-iii.org
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

namespace App\Services\LunchFlow\Conversion\Routine;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Exceptions\RateLimitException;
use App\Services\LunchFlow\Authentication\SecretManager;
use App\Services\LunchFlow\Request\GetTransactionsRequest;
use App\Services\LunchFlow\Response\GetTransactionsResponse;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\CreatesAccounts;
use App\Services\Shared\Conversion\ProgressInformation;
use App\Support\Internal\CollectsAccounts;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Class TransactionProcessor
 */
class TransactionProcessor
{
    use CollectsAccounts;
    use CreatesAccounts;
    use ProgressInformation;

    /** @var string */
    private const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    private array         $accounts;
    private Configuration $configuration;
    private ?Carbon       $notAfter       = null;
    private ?Carbon       $notBefore      = null;

    /**
     * @throws ImporterErrorException
     */
    public function download(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $this->notBefore               = null;
        $this->notAfter                = null;
        $this->accounts                = [];
        if ('' !== $this->configuration->getDateNotBefore()) {
            $this->notBefore = new Carbon($this->configuration->getDateNotBefore());
        }

        if ('' !== $this->configuration->getDateNotAfter()) {
            $this->notAfter = new Carbon($this->configuration->getDateNotAfter());
        }
        $accounts                      = $this->configuration->getAccounts();
        $return                        = [];
        Log::debug(sprintf('Found %d accounts to download from.', count($accounts)));
        $total                         = count($accounts);

        $this->existingServiceAccounts = $this->getLunchFlowAccounts($this->configuration);

        /**
         * @var int $importServiceAccountId
         * @var int $fireflyIIIAccountId
         */
        foreach ($accounts as $importServiceAccountId => $fireflyIIIAccountId) {
            Log::debug(sprintf('[%s] Going to download Lunch Flow transactions for account #%d', config('importer.version'), $importServiceAccountId));

            // first create the account if it does not exist.
            if (0 === $fireflyIIIAccountId) {
                $createdAccount                           = $this->createOrFindExistingAccount((string) $importServiceAccountId);
                $updatedAccounts                          = $this->configuration->getAccounts();
                $updatedAccounts[$importServiceAccountId] = $createdAccount->id;
                $this->configuration->setAccounts($updatedAccounts);
                // $accounts = $this->configuration->getAccounts();
            }


            $apiToken                        = SecretManager::getApiKey($this->configuration);

            $request                         = new GetTransactionsRequest($apiToken, $importServiceAccountId);
            $request->setBase(config('lunchflow.api_url'));
            $request->setTimeOut(config('importer.connection.timeout'));

            /** @var GetTransactionsResponse $transactions */
            try {
                $transactions = $request->get();
                Log::debug(sprintf('GetTransactionsResponse: count %d transaction(s)', count($transactions)));
            } catch (ImporterHttpException|RateLimitException $e) {
                Log::debug(sprintf('Ran into %s instead of GetTransactionsResponse', $e::class));
                $this->addWarning(0, $e->getMessage());
                $return[$importServiceAccountId] = [];


                continue;
            } catch (AgreementExpiredException $e) {
                Log::debug(sprintf('Ran into %s instead of GetTransactionsResponse', $e::class));
                // agreement expired, whoops.
                $return[$importServiceAccountId] = [];
                $this->addError(0, $e->json['detail'] ?? '[a114]: Your EUA has expired.');

                continue;
            }

            $return[$importServiceAccountId] = $this->filterTransactions($transactions);
            Log::debug(sprintf('[%s] Going to download Lunch Flow transactions for account #%d', config('importer.version'), $importServiceAccountId));
        }
        Log::debug('Done with download of transactions.');

        return $return;
    }

    public function getAccounts(): array
    {
        return $this->accounts;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    private function filterTransactions(GetTransactionsResponse $transactions): array
    {
        Log::info(sprintf('Going to filter downloaded transactions. Original set length is %d', count($transactions)));
        if ($this->notBefore instanceof Carbon) {
            Log::info(sprintf('Will not grab transactions before "%s"', $this->notBefore->format('Y-m-d H:i:s')));
        }
        if ($this->notAfter instanceof Carbon) {
            Log::info(sprintf('Will not grab transactions after "%s"', $this->notAfter->format('Y-m-d H:i:s')));
        }
        $return     = [];
        $getPending = $this->configuration->getPendingTransactions();
        if ($getPending) {
            Log::info('Will include pending transactions.');
        }
        if (!$getPending) {
            Log::info('Will NOT include pending transactions.');
        }
        foreach ($transactions as $transaction) {
            $madeOn   = $transaction->getDate();

            if ($this->notBefore instanceof Carbon && $madeOn->lt($this->notBefore)) {
                Log::debug(sprintf('Skip transaction because "%s" is before "%s".', $madeOn->format(self::DATE_TIME_FORMAT), $this->notBefore->format(self::DATE_TIME_FORMAT)));

                continue;
            }
            if ($this->notAfter instanceof Carbon && $madeOn->gt($this->notAfter)) {
                Log::debug(sprintf('Skip transaction because "%s" is after "%s".', $madeOn->format(self::DATE_TIME_FORMAT), $this->notAfter->format(self::DATE_TIME_FORMAT)));

                continue;
            }
            // add error if amount is zero:
            if (0 === bccomp('0', $transaction->amount)) {
                $this->addWarning(0, sprintf('Transaction #%s ("%s", "%s", "%s") has an amount of zero and has been ignored..', $transaction->id, $transaction->account, $transaction->getDestinationName(), $transaction->getDescription()));
                Log::debug(sprintf('Skip transaction because amount is zero: "%s".', $transaction->amount));

                continue;
            }

            Log::debug(sprintf('Include transaction because date is "%s".', $madeOn->format(self::DATE_TIME_FORMAT)));

            $return[] = $transaction;
        }
        Log::info(sprintf('After filtering, set is %d transaction(s)', count($return)));

        return $return;
    }

    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function getRateLimits(): array
    {
        return $this->rateLimits;
    }
}
