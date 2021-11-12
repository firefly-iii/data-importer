<?php
declare(strict_types=1);
/**
 * CSVFileProcessor.php
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

namespace App\Services\Import\Routine;

use App\Exceptions\ImportException;
use App\Services\CSV\Configuration\Configuration;
use App\Services\CSV\Specifics\SpecificService;
use App\Services\Import\Support\ProgressInformation;
use JsonException;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\ResultSet;
use League\Csv\Statement;
use Log;

/**
 * Class CSVFileProcessor
 */
class CSVFileProcessor
{
    use ProgressInformation;

    private bool          $hasHeaders;
    private Reader        $reader;
    private array         $specifics;
    private string        $delimiter;
    private Configuration $configuration;

    /**
     * CSVFileProcessor constructor.
     *
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->specifics     = [];
        $this->configuration = $configuration;
    }


    /**
     * @param string $delimiter
     */
    public function setDelimiter(string $delimiter): void
    {
        $map = [
            'tab'       => "\t",
            'semicolon' => ';',
            'comma'     => ',',
        ];

        $this->delimiter = $map[$delimiter] ?? ',';
    }

    /**
     * Get a reader, and start looping over each line.
     *
     * @return array
     */
    public function processCSVFile(): array
    {
        Log::debug('Now in startImportLoop()');
        $offset = $this->hasHeaders ? 1 : 0;
        try {
            $this->reader->setDelimiter($this->delimiter);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            $message = sprintf('Could not set delimiter: %s', $e->getMessage());
            $this->addError(0, $message);
            return [];
        }
        Log::debug(sprintf('Offset is %d', $offset));
        try {
            $stmt    = (new Statement)->offset($offset);
            $records = $stmt->process($this->reader);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            $message = sprintf('Could not read CSV: %s', $e->getMessage());
            $this->addError(0, $message);
            return [];
        }

        try {
            return $this->processCSVLines($records);
        } catch (ImportException $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            $message = sprintf('Could not parse CSV: %s', $e->getMessage());
            $this->addError(0, $message);
            return [];
        }
    }

    /**
     * Loop all records from CSV file.
     *
     * @param ResultSet $records
     *
     * @return array
     * @throws ImportException
     */
    private function processCSVLines(ResultSet $records): array
    {
        $updatedRecords = [];
        $count          = $records->count();
        Log::info(sprintf('Now in %s with %d records', __METHOD__, $count));
        $currentIndex = 1;
        foreach ($records as $line) {
            $line = $this->sanitize($line);
            Log::debug(sprintf('Parsing line %d/%d', $currentIndex, $count));
            $line             = SpecificService::runSpecifics($line, $this->specifics);
            $updatedRecords[] = $line;
            $currentIndex++;

        }
        Log::info(sprintf('Parsed all %d lines.', $count));

        // exclude double lines.
        if ($this->configuration->isIgnoreDuplicateLines()) {
            Log::info('Going to remove duplicate lines.');
            $updatedRecords = $this->removeDuplicateLines($updatedRecords);
        }

        return $updatedRecords;
    }

    /**
     * Do a first sanity check on whatever comes out of the CSV file.
     *
     * @param array $line
     *
     * @return array
     */
    private function sanitize(array $line): array
    {
        $lineValues = array_values($line);
        array_walk(
            $lineValues, static function ($element) {
            return trim(str_replace('&nbsp;', ' ', (string) $element));
        }
        );

        return $lineValues;
    }

    /**
     * @param array $array
     *
     * @return array
     * @throws ImportException
     */
    private function removeDuplicateLines(array $array): array
    {
        $hashes = [];
        $return = [];
        foreach ($array as $index => $line) {
            try {
                $hash = hash('sha256', json_encode($line, JSON_THROW_ON_ERROR));
            } catch (JsonException $e) {
                Log::error($e->getMessage());
                Log::error($e->getTraceAsString());
                throw new ImportException(sprintf('Could not decode JSON line #%d: %s', $index, $e->getMessage()));
            }
            if (in_array($hash, $hashes, true)) {
                $message = sprintf('Going to skip line #%d because it\'s in the file twice. This may reset the count below.', $index);
                Log::warning($message);
                $this->addWarning($index, $message);
            }
            if (!in_array($hash, $hashes, true)) {
                $hashes[] = $hash;
                $return[] = $line;
            }
        }
        Log::info(sprintf('Went from %d line(s) to %d line(s)', count($array), count($return)));

        return $return;
    }

    /**
     * @param bool $hasHeaders
     */
    public function setHasHeaders(bool $hasHeaders): void
    {
        $this->hasHeaders = $hasHeaders;
    }

    /**
     * @param Reader $reader
     */
    public function setReader(Reader $reader): void
    {
        $this->reader = $reader;
    }

    /**
     * @param array $specifics
     */
    public function setSpecifics(array $specifics): void
    {
        $this->specifics = $specifics;
    }

}
