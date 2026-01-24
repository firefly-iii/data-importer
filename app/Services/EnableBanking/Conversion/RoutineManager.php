<?php

/*
 * RoutineManager.php
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

namespace App\Services\EnableBanking\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\EnableBanking\Conversion\Routine\GenerateTransactions;
use App\Services\EnableBanking\Conversion\Routine\TransactionProcessor;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use Illuminate\Support\Facades\Log;
use Override;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    private Configuration $configuration;
    private GenerateTransactions $transactionGenerator;
    private TransactionProcessor $transactionProcessor;
    private ImportJobRepository $repository;
    private ImportJob $importJob;
    private array $downloaded;

    public function __construct(ImportJob $importJob)
    {
        $this->downloaded = [];
        $this->transactionProcessor = new TransactionProcessor();
        $this->transactionGenerator = new GenerateTransactions();
        $this->repository = new ImportJobRepository();
        $this->importJob = $importJob;
        $this->importJob->refreshInstanceIdentifier();
        $this->setConfiguration($this->importJob->getConfiguration());
    }

    #[Override]
    public function getServiceAccounts(): array
    {
        Log::debug(sprintf('RoutineManager.getServiceAccounts(%d)', count($this->importJob->getServiceAccounts())));

        return $this->importJob->getServiceAccounts();
    }

    /**
     * @throws ImporterErrorException
     */
    private function setConfiguration(Configuration $configuration): void
    {
        Log::debug('RoutineManager.setConfiguration');
        $this->configuration = $configuration;

        $this->transactionProcessor->setImportJob($this->importJob);
        $this->transactionGenerator->setImportJob($this->importJob);
    }

    /**
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        Log::debug(sprintf('The Enable Banking API URL is %s', config('enablebanking.url')));

        $this->transactionProcessor->setExistingServiceAccounts($this->getServiceAccounts());

        // Step 1: get transactions from Enable Banking
        $this->downloadFromEnableBanking();

        // Step 2: Generate Firefly III-ready transactions
        $this->collectTargetAccounts();

        // Check if we downloaded anything
        if (true === $this->breakOnDownload()) {
            return [];
        }

        // Generate the transactions
        $transactions = $this->transactionGenerator->getTransactions($this->downloaded);
        Log::debug(sprintf('Generated %d Firefly III transactions.', count($transactions)));

        return $transactions;
    }

    /**
     * @throws ImporterErrorException
     */
    private function downloadFromEnableBanking(): void
    {
        Log::debug('Call transaction processor download.');

        try {
            $this->downloaded = $this->transactionProcessor->download();
        } catch (ImporterErrorException $e) {
            Log::error('Could not download transactions from Enable Banking.');
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

            $this->importJob->conversionStatus->addError(0, sprintf('[eb001]: Could not download from Enable Banking: %s', $e->getMessage()));
            $this->repository->saveToDisk($this->importJob);

            throw $e;
        }

        $this->importJob = $this->transactionProcessor->getImportJob();
        $this->setConfiguration($this->importJob->getConfiguration());
        $this->repository->saveToDisk($this->importJob);
    }

    private function collectTargetAccounts(): void
    {
        Log::debug('Generating Firefly III transactions.');

        try {
            $this->transactionGenerator->collectTargetAccounts();
        } catch (ApiHttpException $e) {
            $this->importJob->conversionStatus->addError(0, sprintf('[eb002]: Error while collecting target accounts: %s', $e->getMessage()));
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
            $this->importJob->conversionStatus->addError(0, '[eb003]: No transactions were downloaded from Enable Banking.');
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
