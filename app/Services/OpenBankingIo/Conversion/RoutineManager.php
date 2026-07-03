<?php

/*
 * RoutineManager.php
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

namespace App\Services\OpenBankingIo\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\OpenBankingIo\Conversion\Routine\GenerateTransactions;
use App\Services\OpenBankingIo\Conversion\Routine\TransactionProcessor;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use Illuminate\Support\Facades\Log;
use Override;

/**
 * Class RoutineManager
 */
final class RoutineManager implements RoutineManagerInterface
{
    private GenerateTransactions $transactionGenerator;
    private TransactionProcessor $transactionProcessor;
    private ImportJobRepository $repository;
    private array $downloaded;
    private ImportJob $importJob;

    public function __construct(ImportJob $importJob)
    {
        $this->downloaded           = [];
        $this->repository           = new ImportJobRepository();
        $this->importJob            = $importJob;
        $this->importJob->refreshInstanceIdentifier();

        $this->transactionProcessor = new TransactionProcessor();
        $this->transactionGenerator = new GenerateTransactions();
        $this->setConfiguration();
    }

    #[Override]
    public function getServiceAccounts(): array
    {
        return $this->transactionProcessor->getAccounts();
    }

    private function setConfiguration(): void
    {
        $this->transactionProcessor->setImportJob($this->importJob);
        $this->transactionGenerator->setImportJob($this->importJob);
    }

    /**
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        // Step 1: download + decrypt transactions from open-banking.io
        $this->downloadFromOpenBankingIo();

        // Step 2: collect target accounts from Firefly III
        $this->collectTargetAccounts();

        $this->importJob = $this->transactionProcessor->getImportJob();
        $this->transactionGenerator->setImportJob($this->importJob);

        if (true === $this->breakOnDownload()) {
            return [];
        }

        // Step 3: generate Firefly III-ready transactions
        $transactions    = $this->transactionGenerator->getTransactions($this->downloaded);
        Log::debug(sprintf('Generated %d Firefly III transactions.', count($transactions)));

        return $transactions;
    }

    /**
     * @throws ImporterErrorException
     */
    private function downloadFromOpenBankingIo(): void
    {
        Log::debug('Call open-banking.io transaction processor download.');

        try {
            $this->downloaded = $this->transactionProcessor->download();
        } catch (ImporterErrorException $e) {
            Log::error('Could not download transactions from open-banking.io.');
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
            $this->importJob->conversionStatus->addError(0, sprintf('[a109]: Could not download from open-banking.io: %s', e($e->getMessage())));
            $this->repository->saveToDisk($this->importJob);

            throw $e;
        }
    }

    private function collectTargetAccounts(): void
    {
        Log::debug('Generating Firefly III transactions.');

        try {
            $this->transactionGenerator->collectTargetAccounts();
        } catch (ApiHttpException $e) {
            $this->importJob->conversionStatus->addError(0, sprintf('[a110]: Error while collecting target accounts: %s', e($e->getMessage())));
            $this->repository->saveToDisk($this->importJob);

            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }
    }

    private function breakOnDownload(): bool
    {
        $total = 0;
        foreach ($this->downloaded as $transactions) {
            $total += count($transactions);
        }
        if (0 === $total) {
            Log::warning('Downloaded nothing, will return nothing.');
            $this->importJob->conversionStatus->addError(
                0,
                '[a111]: No transactions were downloaded from open-banking.io. You may need to reconnect the bank or widen the date range.'
            );
            $this->repository->saveToDisk($this->importJob);

            return true;
        }

        return false;
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }
}
