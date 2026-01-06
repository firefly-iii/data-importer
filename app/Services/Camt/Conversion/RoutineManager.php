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

namespace App\Services\Camt\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\CombinedProgressInformation;
use App\Services\Shared\Conversion\ProgressInformation;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use Genkgo\Camt\Config;
use Genkgo\Camt\DTO\Message;
use Genkgo\Camt\Exception\InvalidMessageException;
use Genkgo\Camt\Reader;
use Illuminate\Support\Facades\Log;
use Override;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{

    private TransactionConverter $transactionConverter;
    private TransactionExtractor $transactionExtractor;
    private TransactionMapper    $transactionMapper;
    private ImportJob            $importJob;
    private ImportJobRepository  $repository;

    public function __construct(ImportJob $importJob)
    {
        Log::debug('Constructed CAMT RoutineManager');
        $this->importJob     = $importJob;
        $this->repository    = new ImportJobRepository();
        $this->importJob->refreshInstanceIdentifier();
        $this->setConfiguration($this->importJob->getConfiguration());
    }

    #[Override]
    public function getServiceAccounts(): array
    {
        return [];
    }

    /**
     * @throws ImporterErrorException
     */
    private function setConfiguration(Configuration $configuration): void
    {
        // make objects
        $this->transactionExtractor = new TransactionExtractor($configuration);
        $this->transactionConverter = new TransactionConverter($configuration);
        $this->transactionMapper    = new TransactionMapper($configuration);
    }

    public function start(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        // get XML file
        $camtMessage        = $this->getCamtMessage();
        if (!$camtMessage instanceof Message) {
            Log::error('The CAMT object is NULL, probably due to a previous error');
            $this->importJob->conversionStatus->addError(0, '[a102]: The CAMT object is NULL, probably due to a previous error');
            $this->repository->saveToDisk($this->importJob);
            return [];
        }
        // get raw messages
        $rawTransactions    = $this->transactionExtractor->extractTransactions($camtMessage);

        // get intermediate result (still needs processing like mapping etc)
        $pseudoTransactions = $this->transactionConverter->convert($rawTransactions);

        // put the result into firefly iii compatible arrays (and replace mapping when necessary)
        $transactions       = $this->transactionMapper->map($pseudoTransactions);

        if (0 === count($transactions)) {
            Log::error('No transactions found in CAMT file');
            $this->importJob->conversionStatus->addError(0, '[a103]: No transactions found in CAMT file.');
            $this->repository->saveToDisk($this->importJob);

            return [];
        }
        $this->repository->saveToDisk($this->importJob);

        return $transactions;
    }

    private function getCamtMessage(): ?Message
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $camtReader = new Reader(Config::getDefault());

        try {
            $camtMessage = $camtReader->readString($this->importJob->getImportableFileString()); // -> Level A
        } catch (InvalidMessageException $e) {
            Log::error('Conversion error in RoutineManager::getCamtMessage');
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
            $this->importJob->conversionStatus->addError(0, sprintf('[a104]: Could not convert CAMT.x file: %s', $e->getMessage()));

            return null;
        }

        return $camtMessage;
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }
}
