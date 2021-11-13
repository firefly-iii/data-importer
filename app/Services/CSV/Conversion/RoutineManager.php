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

namespace App\Services\CSV\Conversion;

use App\Services\CSV\Configuration\Configuration;
use App\Services\Import\Routine\CSVFileProcessor;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use Log;
use Storage;
use Str;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    private const DISK_NAME = 'conversion-routines';

    private Configuration    $configuration;
    private string           $identifier;
    private CSVFileProcessor $csvFileProcessor;

    /**
     *
     */
    public function __construct(?string $identifier)
    {
        if (null === $identifier) {
            $this->generateIdentifier();
        }
        if (null !== $identifier) {
            $this->identifier = $identifier;
        }
    }

    /**
     * @inheritDoc
     */
    public function setConfiguration(Configuration $configuration): void
    {
        // save config
        $this->configuration    = $configuration;

        // share config
        $this->csvFileProcessor = new CSVFileProcessor($this->configuration);

        // set identifier:
        $this->csvFileProcessor->setIdentifier($this->identifier);
    }

    /**
     * @inheritDoc
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

        // save transactions to disk
        die('TODO save to disk!');

        $count = count($CSVLines);
        $this->mergeMessages($count);
        $this->mergeWarnings($count);
        $this->mergeErrors($count);
    }

    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     *
     */
    private function generateIdentifier(): void
    {
        Log::debug('Going to generate conversion routine identifier.');
        $disk  = Storage::disk(self::DISK_NAME);
        $count = 0;
        do {
            $generatedId = sprintf('conv-%s', Str::random(12));
            $count++;
            Log::debug(sprintf('Attempt #%d results in "%s"', $count, $generatedId));
        } while ($count < 30 && $disk->exists($generatedId));
        $this->identifier = $generatedId;
        Log::info(sprintf('Job identifier is "%s"', $generatedId));
    }
}
