<?php
declare(strict_types=1);
/**
 * ImportRoutineManager.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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

namespace App\Services\Import;

use App\Exceptions\ImportException;
use App\Services\CSV\Configuration\Configuration;
use App\Services\Import\ImportJobStatus\ImportJobStatusManager;
use App\Services\Import\Routine\APISubmitter;
use App\Services\Import\Routine\ColumnValueConverter;
use App\Services\Import\Routine\CSVFileProcessor;
use App\Services\Import\Routine\LineProcessor;
use App\Services\Import\Routine\PseudoTransactionProcessor;
use JsonException;
use League\Csv\Reader;
use Log;
use Storage;
use Str;

/**
 * Class ImportRoutineManager
 */
class ImportRoutineManager
{
    /** @var Configuration */
    private $configuration;
    /** @var LineProcessor */
    private $lineProcessor;
    /** @var ColumnValueConverter */
    private $columnValueConverter;
    /** @var PseudoTransactionProcessor */
    private $pseudoTransactionProcessor;
    /** @var APISubmitter */
    private $apiSubmitter;
    /** @var CSVFileProcessor */
    private $csvFileProcessor;
    /** @var string */
    private $identifier;
    /** @var array */
    private $allMessages;
    /** @var array */
    private $allWarnings;
    /** @var array */
    private $allErrors;

    /**
     * Collect info on the current job, hold it in memory.
     *
     * ImportRoutineManager constructor.
     *
     * @param string|null $identifier
     *
     * @throws JsonException
     */
    public function __construct(string $identifier = null)
    {
        Log::debug('Constructed ImportRoutineManager');

        // get line converter
        $this->allMessages = [];
        $this->allWarnings = [];
        $this->allErrors   = [];
        if (null === $identifier) {
            $this->generateIdentifier();
        }
        if (null !== $identifier) {
            $this->identifier = $identifier;
        }
        ImportJobStatusManager::startOrFindJob($this->identifier);
    }

    /**
     *
     */
    private function generateIdentifier(): void
    {
        Log::debug('Going to generate identifier.');
        $disk  = Storage::disk('jobs');
        $count = 0;
        do {
            $generatedId = Str::random(16);
            $count++;
            Log::debug(sprintf('Attempt #%d results in "%s"', $count, $generatedId));
        } while ($count < 30 && $disk->exists($generatedId));
        $this->identifier = $generatedId;
        Log::info(sprintf('Job identifier is "%s"', $generatedId));
    }

    /**
     * @param Configuration $configuration
     *
     * @throws ImportException
     */
    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration              = $configuration;
        $this->apiSubmitter               = new APISubmitter;
        $this->lineProcessor              = new LineProcessor($this->configuration);
        $this->pseudoTransactionProcessor = new PseudoTransactionProcessor($this->configuration->getDefaultAccount());
        $this->columnValueConverter       = new ColumnValueConverter($this->configuration);
        $this->csvFileProcessor           = new CSVFileProcessor($this->configuration);

        // set the identifier:
        $this->apiSubmitter->setIdentifier($this->identifier);
        $this->lineProcessor->setIdentifier($this->identifier);
        $this->pseudoTransactionProcessor->setIdentifier($this->identifier);
        $this->columnValueConverter->setIdentifier($this->identifier);
        $this->csvFileProcessor->setIdentifier($this->identifier);

        // set config:
        $this->apiSubmitter->setConfiguration($this->configuration);

    }

    /**
     * @param Reader $reader
     */
    public function setReader(Reader $reader): void
    {
        $this->csvFileProcessor->setReader($reader);
    }

    /**
     * @return array
     */
    public function getAllMessages(): array
    {
        return $this->allMessages;
    }

    /**
     * @return array
     */
    public function getAllWarnings(): array
    {
        return $this->allWarnings;
    }

    /**
     * @return array
     */
    public function getAllErrors(): array
    {
        return $this->allErrors;
    }

    /**
     * Start the import.
     */
    public function start(): void
    {
        Log::debug(sprintf('Now in %s', __METHOD__));

        // convert CSV file into raw lines (arrays)
        $this->csvFileProcessor->setSpecifics($this->configuration->getSpecifics());
        $this->csvFileProcessor->setHasHeaders($this->configuration->isHeaders());
        $this->csvFileProcessor->setDelimiter($this->configuration->getDelimiter());
        $CSVLines = $this->csvFileProcessor->processCSVFile();

        // convert raw lines into arrays with individual ColumnValues
        $valueArrays = $this->lineProcessor->processCSVLines($CSVLines);

        // convert value arrays into (pseudo) transactions.
        $pseudo = $this->columnValueConverter->processValueArrays($valueArrays);

        // convert pseudo transactions into actual transactions.
        $transactions = $this->pseudoTransactionProcessor->processPseudo($pseudo);

        // submit transactions to API:
        $this->apiSubmitter->processTransactions($transactions);

        $count = count($CSVLines);
        $this->mergeMessages($count);
        $this->mergeWarnings($count);
        $this->mergeErrors($count);
    }

    /**
     * @param int $count
     */
    private function mergeMessages(int $count): void
    {
        $one   = $this->csvFileProcessor->getMessages();
        $two   = $this->lineProcessor->getMessages();
        $three = $this->columnValueConverter->getMessages();
        $four  = $this->pseudoTransactionProcessor->getMessages();
        $five  = $this->apiSubmitter->getMessages();
        $total = [];
        for ($i = 0; $i < $count; $i++) {
            $total[$i] = array_merge(
                $one[$i] ?? [],
                $two[$i] ?? [],
                $three[$i] ?? [],
                $four[$i] ?? [],
                $five[$i] ?? []
            );
        }

        $this->allMessages = $total;
    }

    /**
     * @param int $count
     */
    private function mergeWarnings(int $count): void
    {
        $one   = $this->csvFileProcessor->getWarnings();
        $two   = $this->lineProcessor->getWarnings();
        $three = $this->columnValueConverter->getWarnings();
        $four  = $this->pseudoTransactionProcessor->getWarnings();
        $five  = $this->apiSubmitter->getWarnings();
        $total = [];
        for ($i = 0; $i < $count; $i++) {
            $total[$i] = array_merge(
                $one[$i] ?? [],
                $two[$i] ?? [],
                $three[$i] ?? [],
                $four[$i] ?? [],
                $five[$i] ?? []
            );
        }

        $this->allWarnings = $total;
    }

    /**
     * @param int $count
     */
    private function mergeErrors(int $count): void
    {
        $one   = $this->csvFileProcessor->getErrors();
        $two   = $this->lineProcessor->getErrors();
        $three = $this->columnValueConverter->getErrors();
        $four  = $this->pseudoTransactionProcessor->getErrors();
        $five  = $this->apiSubmitter->getErrors();
        $total = [];
        for ($i = 0; $i < $count; $i++) {
            $total[$i] = array_merge(
                $one[$i] ?? [],
                $two[$i] ?? [],
                $three[$i] ?? [],
                $four[$i] ?? [],
                $five[$i] ?? []
            );
        }

        $this->allErrors = $total;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

}
