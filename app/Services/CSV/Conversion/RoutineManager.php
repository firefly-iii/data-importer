<?php

/*
 * RoutineManager.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
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
use App\Services\CSV\Conversion\Routine\ColumnValueConverter;
use App\Services\CSV\Conversion\Routine\CSVFileProcessor;
use App\Services\CSV\Conversion\Routine\LineProcessor;
use App\Services\CSV\Conversion\Routine\PseudoTransactionProcessor;
use App\Services\CSV\File\FileReader;
use App\Services\Shared\Authentication\IsRunningCli;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\CombinedProgressInformation;
use App\Services\Shared\Conversion\GeneratesIdentifier;
use App\Services\Shared\Conversion\ProgressInformation;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use Illuminate\Support\Facades\Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Override;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    use CombinedProgressInformation;
    use GeneratesIdentifier;
    use IsRunningCli;
    use ProgressInformation;

    private ColumnValueConverter       $columnValueConverter;
    private Configuration              $configuration;
    private string                     $content;
    private CSVFileProcessor           $csvFileProcessor;
    private LineProcessor              $lineProcessor;
    private PseudoTransactionProcessor $pseudoTransactionProcessor;

    public function __construct(?string $identifier)
    {
        $this->content       = '';    // used in CLI
        $this->allErrors     = [];
        $this->allWarnings   = [];
        $this->allMessages   = [];
        $this->allRateLimits = [];
        if (null === $identifier) {
            $this->generateIdentifier();
        }
        if (null !== $identifier) {
            $this->identifier = $identifier;
        }
    }

    #[Override]
    public function getServiceAccounts(): array
    {
        return [];
    }

    /**
     * @throws ImporterErrorException
     */
    public function setConfiguration(Configuration $configuration): void
    {
        // save config
        $this->configuration              = $configuration;

        // share config
        $this->csvFileProcessor           = new CSVFileProcessor($this->configuration);
        $this->lineProcessor              = new LineProcessor($this->configuration);
        $this->columnValueConverter       = new ColumnValueConverter($this->configuration);
        $this->pseudoTransactionProcessor = new PseudoTransactionProcessor($this->configuration->getDefaultAccount());

        // set identifier:
        $this->csvFileProcessor->setIdentifier($this->identifier);
        $this->lineProcessor->setIdentifier($this->identifier);
        $this->columnValueConverter->setIdentifier($this->identifier);
        $this->pseudoTransactionProcessor->setIdentifier($this->identifier);
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
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

        // check if CLI or not and read as appropriate:
        if ('' !== $this->content) {
            $this->csvFileProcessor->setReader(FileReader::getReaderFromContent($this->content, $this->configuration->isConversion()));
        }
        if ('' === $this->content) {
            try {
                $this->csvFileProcessor->setReader(FileReader::getReaderFromSession($this->configuration->isConversion()));
            } catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
                throw new ImporterErrorException($e->getMessage(), 0, $e);
            }
        }

        $CSVLines     = $this->csvFileProcessor->processCSVFile();

        // convert raw lines into arrays with individual ColumnValues
        $valueArrays  = $this->lineProcessor->processCSVLines($CSVLines);

        // convert value arrays into (pseudo) transactions.
        $pseudo       = $this->columnValueConverter->processValueArrays($valueArrays);

        // convert pseudo transactions into actual transactions.
        $transactions = $this->pseudoTransactionProcessor->processPseudo($pseudo);

        $count        = count($CSVLines);

        // debug messages on weird indexes.
        //        $this->addError(3, '3: No transactions found in CSV file.');
        //        $this->addMessage(5, '5: No transactions found in CSV file.');
        //        $this->addWarning(7, '7: No transactions found in CSV file.');

        if (0 === $count) {
            $this->addError(0, '[a105]: No transactions found in CSV file.');
            $this->mergeMessages(1);
            $this->mergeWarnings(1);
            $this->mergeErrors(1);

            return [];
        }

        $this->mergeMessages($count);
        $this->mergeWarnings($count);
        $this->mergeErrors($count);

        return $transactions;
    }

    private function mergeMessages(int $count): void
    {
        $this->allMessages = $this->mergeArrays(
            [
                $this->getMessages(),
                $this->csvFileProcessor->getMessages(),
                $this->lineProcessor->getMessages(),
                $this->columnValueConverter->getMessages(),
                $this->pseudoTransactionProcessor->getMessages(),
            ],
            $count
        );
    }

    private function mergeWarnings(int $count): void
    {
        $this->allWarnings = $this->mergeArrays(
            [
                $this->getWarnings(),
                $this->csvFileProcessor->getWarnings(),
                $this->lineProcessor->getWarnings(),
                $this->columnValueConverter->getWarnings(),
                $this->pseudoTransactionProcessor->getWarnings(),
            ],
            $count
        );
    }

    private function mergeErrors(int $count): void
    {
        $this->allErrors = $this->mergeArrays(
            [
                $this->getErrors(),
                $this->csvFileProcessor->getErrors(),
                $this->lineProcessor->getErrors(),
                $this->columnValueConverter->getErrors(),
                $this->pseudoTransactionProcessor->getErrors(),
            ],
            $count
        );
    }
}
