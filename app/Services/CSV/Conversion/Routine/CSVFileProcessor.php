<?php

/*
 * CSVFileProcessor.php
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

namespace App\Services\CSV\Conversion\Routine;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Services\Shared\Configuration\Configuration;
use Illuminate\Support\Facades\Log;
use JsonException;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\ResultSet;
use League\Csv\Statement;

/**
 * Class CSVFileProcessor
 */
final class CSVFileProcessor
{
    private string $delimiter;
    private bool $hasHeaders;
    private int $skipFirstLines = 0;
    private int $skipLastLines = 0;
    private Reader $reader;
    private ImportJob $importJob;
    private Configuration $configuration;

    /**
     * CSVFileProcessor constructor.
     */
    public function __construct(ImportJob $importJob)
    {
        $importJob->refreshInstanceIdentifier();
        $this->importJob     = $importJob;
        $this->configuration = $importJob->getConfiguration();
    }

    /**
     * Get a reader, and start looping over each line.
     */
    public function processCSVFile(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        // Calculate total offset: skip_first_lines + (1 if headers)
        $offset = $this->skipFirstLines + ($this->hasHeaders ? 1 : 0);

        Log::debug(sprintf(
            'Skip first lines: %d, Has headers: %s, Total offset: %d',
            $this->skipFirstLines,
            $this->hasHeaders ? 'yes' : 'no',
            $offset
        ));

        try {
            $this->reader->setDelimiter($this->delimiter);
        } catch (Exception $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
            // Log::error($e->getTraceAsString());
            $message = sprintf('[a106]: Could not set delimiter: %s', e($e->getMessage()));
            $this->importJob->conversionStatus->addError(0, $message);

            return [];
        }
        Log::debug(sprintf('Offset is %d', $offset));

        try {
            $stmt    = new Statement()->offset($offset);

            /** @var ResultSet $records */
            $records = $stmt->process($this->reader);
        } catch (Exception $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
            //            Log::error($e->getTraceAsString());
            $message = sprintf('[a107]: Could not read CSV: %s', e($e->getMessage()));
            $this->importJob->conversionStatus->addError(0, $message);

            return [];
        }

        try {
            return $this->processCSVLines($records);
        } catch (ImporterErrorException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
            //            Log::error($e->getTraceAsString());
            $message = sprintf('[a108]: Could not parse CSV: %s', e($e->getMessage()));
            $this->importJob->conversionStatus->addError(0, $message);

            return [];
        }
    }

    public function setDelimiter(string $delimiter): void
    {
        $map             = ['tab' => "\t", 'semicolon' => ';', 'comma' => ','];

        $this->delimiter = $map[$delimiter] ?? ',';
    }

    public function setSkipFirstLines(int $lines): void
    {
        $this->skipFirstLines = $lines;
    }

    public function setSkipLastLines(int $lines): void
    {
        $this->skipLastLines = $lines;
    }

    /**
     * Loop all records from CSV file.
     *
     * @throws ImporterErrorException
     */
    private function processCSVLines(ResultSet $records): array
    {
        $updatedRecords = [];
        $count          = $records->count();
        Log::info(sprintf('Now in %s with %d records', __METHOD__, $count));

        // Calculate how many lines to actually process
        $linesToProcess = $this->skipLastLines > 0
            ? $count - $this->skipLastLines
            : $count;

        Log::debug(sprintf(
            'Total records: %d, Skip last lines: %d, Lines to process: %d',
            $count,
            $this->skipLastLines,
            $linesToProcess
        ));

        $currentIndex   = 1;
        foreach ($records as $line) {
            // Stop processing if we've reached the lines to skip at the end
            if ($currentIndex > $linesToProcess) {
                Log::debug(sprintf(
                    'Skipping line %d/%d (part of last %d lines)',
                    $currentIndex,
                    $count,
                    $this->skipLastLines
                ));
                ++$currentIndex;
                continue;
            }

            $line             = $this->sanitize($line);
            Log::debug(sprintf('Parsing line %d/%d', $currentIndex, $linesToProcess));
            $updatedRecords[] = $line;

            ++$currentIndex;
        }
        Log::info(sprintf(
            'Parsed %d lines (skipped last %d lines).',
            count($updatedRecords),
            $this->skipLastLines
        ));

        // exclude double lines.
        if ($this->configuration->isIgnoreDuplicateLines()) {
            Log::info('Going to remove duplicate lines.');
            $updatedRecords = $this->removeDuplicateLines($updatedRecords);
        }

        return $updatedRecords;
    }

    /**
     * Do a first sanity check on whatever comes out of the CSV file.
     */
    private function sanitize(array $line): array
    {
        $lineValues = array_values($line);
        array_walk($lineValues, static fn ($element) => trim(str_replace('&nbsp;', ' ', (string) $element)));

        return $lineValues;
    }

    /**
     * @throws ImporterErrorException
     */
    private function removeDuplicateLines(array $array): array
    {
        $hashes = [];
        $return = [];
        foreach ($array as $index => $line) {
            try {
                $hash = hash('sha256', json_encode($line, JSON_THROW_ON_ERROR));
            } catch (JsonException $e) {
                Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

                //                Log::error($e->getTraceAsString());
                throw new ImporterErrorException(sprintf('Could not decode JSON line #%d: %s', $index, $e->getMessage()));
            }
            if (in_array($hash, $hashes, true)) {
                $message = sprintf('Going to skip line #%d because it\'s in the file twice. This may reset the count below.', $index);
                Log::warning(sprintf('[%s] %s', config('importer.version'), $message));
                $this->importJob->conversionStatus->addWarning($index, $message);
            }
            if (!in_array($hash, $hashes, true)) {
                $hashes[] = $hash;
                $return[] = $line;
            }
        }
        Log::info(sprintf('Went from %d line(s) to %d line(s)', count($array), count($return)));

        return $return;
    }

    public function setHasHeaders(bool $hasHeaders): void
    {
        $this->hasHeaders = $hasHeaders;
    }

    public function setReader(Reader $reader): void
    {
        $this->reader = $reader;
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }
}
