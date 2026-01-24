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

namespace App\Services\EnableBanking\Conversion\Routine;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\EnableBanking\Request\GetTransactionsRequest;
use App\Services\EnableBanking\Response\TransactionsResponse;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\CreatesAccounts;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Class TransactionProcessor
 */
class TransactionProcessor
{
    use CreatesAccounts;

    private const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    private array $accounts = [];
    private Configuration $configuration;
    private ?Carbon $notAfter = null;
    private ?Carbon $notBefore = null;
    private ImportJob $importJob;
    private ImportJobRepository $repository;

    /**
     * @throws ImporterErrorException
     */
    public function download(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        $this->notBefore = null;
        $this->notAfter = null;
        $this->accounts = [];

        if ('' !== $this->configuration->getDateNotBefore()) {
            $this->notBefore = new Carbon($this->configuration->getDateNotBefore());
        }

        if ('' !== $this->configuration->getDateNotAfter()) {
            $this->notAfter = new Carbon($this->configuration->getDateNotAfter());
        }

        $accounts = $this->configuration->getAccounts();
        $return = [];

        Log::debug(sprintf('Found %d accounts to download from.', count($accounts)));
        $total = count($accounts);
        $index = 1;

        foreach ($accounts as $accountUid => $destinationId) {
            Log::debug(sprintf('[%s] [%d/%d] Going to download transactions for account "%s" (into #%d)', config('importer.version'), $index, $total, $accountUid, $destinationId));

            if (0 === $destinationId) {
                Log::debug('No destination ID found, create account');
                $destinationId = $this->createNewAccount($accountUid);
                Log::debug(sprintf('Newly created account #%d', $destinationId));
            }

            $url = config('enablebanking.url');
            $dateFrom = '' !== $this->configuration->getDateNotBefore() ? $this->configuration->getDateNotBefore() : null;
            $dateTo = '' !== $this->configuration->getDateNotAfter() ? $this->configuration->getDateNotAfter() : null;

            $request = new GetTransactionsRequest($url, $accountUid, $dateFrom, $dateTo);
            $request->setTimeOut(config('importer.connection.timeout'));

            try {
                /** @var TransactionsResponse $transactions */
                $transactions = $request->get();
                Log::debug(sprintf('TransactionsResponse: count %d transaction(s)', count($transactions)));
            } catch (ImporterHttpException $e) {
                Log::error(sprintf('Enable Banking API error: %s', $e->getMessage()));
                $this->importJob->conversionStatus->addWarning(0, $e->getMessage());
                $return[$accountUid] = [];
                ++$index;

                continue;
            }

            $return[$accountUid] = $this->filterTransactions($transactions);
            Log::debug(sprintf('[%s] [%d/%d] Done downloading transactions for account "%s"', config('importer.version'), $index, $total, $accountUid));
            ++$index;
        }

        Log::debug('Done with download of transactions.');

        return $return;
    }

    public function getAccounts(): array
    {
        return $this->accounts;
    }

    private function filterTransactions(TransactionsResponse $transactions): array
    {
        Log::info(sprintf('Going to filter downloaded transactions. Original set length is %d', count($transactions)));

        if ($this->notBefore instanceof Carbon) {
            Log::info(sprintf('Will not grab transactions before "%s"', $this->notBefore->format('Y-m-d H:i:s')));
        }
        if ($this->notAfter instanceof Carbon) {
            Log::info(sprintf('Will not grab transactions after "%s"', $this->notAfter->format('Y-m-d H:i:s')));
        }

        $return = [];
        $getPending = $this->configuration->getPendingTransactions();

        if ($getPending) {
            Log::info('Will include pending transactions.');
        } else {
            Log::info('Will NOT include pending transactions.');
        }

        foreach ($transactions as $transaction) {
            $madeOn = $transaction->getDate();

            if (!$getPending && 'pending' === $transaction->status) {
                Log::debug(sprintf('Skip pending transaction made on "%s".', $madeOn->format(self::DATE_TIME_FORMAT)));

                continue;
            }

            if ($this->notBefore instanceof Carbon && $madeOn->lt($this->notBefore)) {
                Log::debug(sprintf('Skip transaction because "%s" is before "%s".', $madeOn->format(self::DATE_TIME_FORMAT), $this->notBefore->format(self::DATE_TIME_FORMAT)));

                continue;
            }

            if ($this->notAfter instanceof Carbon && $madeOn->gt($this->notAfter)) {
                Log::debug(sprintf('Skip transaction because "%s" is after "%s".', $madeOn->format(self::DATE_TIME_FORMAT), $this->notAfter->format(self::DATE_TIME_FORMAT)));

                continue;
            }

            if (0 === bccomp('0', $transaction->transactionAmount)) {
                $this->importJob->conversionStatus->addWarning(0, sprintf(
                    'Transaction #%s ("%s") has an amount of zero and has been ignored.',
                    $transaction->transactionId,
                    $transaction->getDescription()
                ));
                Log::debug(sprintf('Skip transaction because amount is zero: "%s".', $transaction->transactionAmount));

                continue;
            }

            Log::debug(sprintf('Include transaction because date is "%s".', $madeOn->format(self::DATE_TIME_FORMAT)));
            $return[] = $transaction;
        }

        Log::info(sprintf('After filtering, set is %d transaction(s)', count($return)));

        return $return;
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function setImportJob(ImportJob $importJob): void
    {
        Log::debug('setImportJob in TransactionProcessor.');
        $importJob->refreshInstanceIdentifier();
        $this->repository = new ImportJobRepository();
        $this->importJob = $importJob;
        $this->configuration = $importJob->getConfiguration();
    }
}
