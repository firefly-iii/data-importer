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

namespace App\Services\CSV\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\CSV\Conversion\Routine\ColumnValueConverter;
use App\Services\CSV\Conversion\Routine\CSVFileProcessor;
use App\Services\CSV\Conversion\Routine\LineProcessor;
use App\Services\CSV\Conversion\Routine\PseudoTransactionProcessor;
use App\Services\CSV\File\FileReader;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\CombinedProgressInformation;
use App\Services\Shared\Conversion\ProgressInformation;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use Illuminate\Support\Facades\Log;
use Override;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    use CombinedProgressInformation;
    use ProgressInformation;

    private ColumnValueConverter       $columnValueConverter;
    private Configuration              $configuration;
    private CSVFileProcessor           $csvFileProcessor;
    private LineProcessor              $lineProcessor;
    private PseudoTransactionProcessor $pseudoTransactionProcessor;
    private ImportJob                  $importJob;
    private ImportJobRepository        $repository;

    public function __construct(ImportJob $importJob)
    {
        $this->allErrors     = [];
        $this->allWarnings   = [];
        $this->allMessages   = [];
        $this->allRateLimits = [];
        $this->importJob     = $importJob;
        $this->configuration = $importJob->getConfiguration();
        $this->repository    = new ImportJobRepository();
        $this->importJob->refreshInstanceIdentifier();

        $this->csvFileProcessor           = new CSVFileProcessor($this->importJob);
        $this->lineProcessor              = new LineProcessor($this->importJob);
        $this->columnValueConverter       = new ColumnValueConverter($this->importJob);
        $this->pseudoTransactionProcessor = new PseudoTransactionProcessor($this->importJob);

    }

    #[Override]
    public function getServiceAccounts(): array
    {
        return [];
    }

    /**
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        // convert CSV file into raw lines (arrays)
        $this->csvFileProcessor->setHasHeaders($this->configuration->isHeaders());
        $this->csvFileProcessor->setDelimiter($this->configuration->getDelimiter());

        $this->csvFileProcessor->setReader(FileReader::getReaderFromContent($this->importJob->getImportableFileString(), $this->configuration->isConversion()));

        // parse CSV lines and get import job back.
        $CSVLines        = $this->csvFileProcessor->processCSVFile();
        $importJob       = $this->csvFileProcessor->getImportJob();
        $this->importJob = $importJob;
        $this->repository->saveToDisk($importJob);

        // convert raw lines into arrays with fresh import job and with individual ColumnValues and get import job back.
        $this->lineProcessor->setImportJob($importJob);
        $valueArrays     = $this->lineProcessor->processCSVLines($CSVLines);
        $importJob       = $this->lineProcessor->getImportJob();
        $this->importJob = $importJob;
        $this->repository->saveToDisk($importJob);

        // convert value arrays into (pseudo) transactions with fresh import job, and get import job back.
        $this->columnValueConverter->setImportJob($importJob);
        $pseudo = $this->columnValueConverter->processValueArrays($valueArrays);
        $importJob       = $this->columnValueConverter->getImportJob();
        $this->importJob = $importJob;
        $this->repository->saveToDisk($importJob);

        // convert pseudo transactions into actual transactions, with fresh import job, and get import job back.
        $this->pseudoTransactionProcessor->setImportJob($importJob);
        $transactions = $this->pseudoTransactionProcessor->processPseudo($pseudo);
        $importJob       = $this->pseudoTransactionProcessor->getImportJob();
        $this->importJob = $importJob;
        $this->repository->saveToDisk($importJob);

        $count = count($CSVLines);

        if (0 === $count) {
            $this->importJob->conversionStatus->addError(0, '[a105]: No transactions found in CSV file.');
            $this->repository->saveToDisk($this->importJob);
            return [];
        }

        return $transactions;
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }
}
