<?php
/*
 * CSVFileProcessor.php
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

namespace App\Services\CSV\Conversion\Routine;

use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use JsonException;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\ResultSet;
use League\Csv\Statement;

/**
 * Class CSVFileProcessor
 */
class CSVFileProcessor
{
    use ProgressInformation;

    private Configuration $configuration;
    private string        $delimiter;
    private bool          $hasHeaders;
    private Reader        $reader;

    /**
     * CSVFileProcessor constructor.
     *
     * @param  Configuration  $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Get a reader, and start looping over each line.
     *
     * @return array
     */
    public function processCSVFile(): array
    {
        app('log')->debug('Now in processCSVFile()');
        $offset = $this->hasHeaders ? 1 : 0;
        try {
            $this->reader->setDelimiter($this->delimiter);
        } catch (Exception $e) {
            app('log')->error($e->getMessage());
            //app('log')->error($e->getTraceAsString());
            $message = sprintf('Could not set delimiter: %s', $e->getMessage());
            $this->addError(0, $message);

            return [];
        }
        app('log')->debug(sprintf('Offset is %d', $offset));
        try {
            $stmt    = (new Statement())->offset($offset);
            $records = $stmt->process($this->reader);
        } catch (Exception $e) {
            app('log')->error($e->getMessage());
            //            app('log')->error($e->getTraceAsString());
            $message = sprintf('Could not read CSV: %s', $e->getMessage());
            $this->addError(0, $message);

            return [];
        }

        try {
            return $this->processCSVLines($records);
        } catch (ImporterErrorException $e) {
            app('log')->error($e->getMessage());
            //            app('log')->error($e->getTraceAsString());
            $message = sprintf('Could not parse CSV: %s', $e->getMessage());
            $this->addError(0, $message);

            return [];
        }
    }

    /**
     * @param  string  $delimiter
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
     * @param  bool  $hasHeaders
     */
    public function setHasHeaders(bool $hasHeaders): void
    {
        $this->hasHeaders = $hasHeaders;
    }

    /**
     * @param  Reader  $reader
     */
    public function setReader(Reader $reader): void
    {
        $this->reader = $reader;
    }

    /**
     * Loop all records from CSV file.
     *
     * @param  ResultSet  $records
     *
     * @return array
     * @throws ImporterErrorException
     */
    private function processCSVLines(ResultSet $records): array
    {
        $updatedRecords = [];
        $count          = $records->count();
        app('log')->info(sprintf('Now in %s with %d records', __METHOD__, $count));
        $currentIndex = 1;
        foreach ($records as $line) {
            $line = $this->sanitize($line);
            app('log')->debug(sprintf('Parsing line %d/%d', $currentIndex, $count));
            $updatedRecords[] = $line;

            $currentIndex++;
        }
        app('log')->info(sprintf('Parsed all %d lines.', $count));

        // exclude double lines.
        if ($this->configuration->isIgnoreDuplicateLines()) {
            app('log')->info('Going to remove duplicate lines.');
            $updatedRecords = $this->removeDuplicateLines($updatedRecords);
        }

        return $updatedRecords;
    }

    /**
     * @param  array  $array
     *
     * @return array
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
                app('log')->error($e->getMessage());
                //                app('log')->error($e->getTraceAsString());
                throw new ImporterErrorException(sprintf('Could not decode JSON line #%d: %s', $index, $e->getMessage()));
            }
            if (in_array($hash, $hashes, true)) {
                $message = sprintf('Going to skip line #%d because it\'s in the file twice. This may reset the count below.', $index);
                app('log')->warning($message);
                $this->addWarning($index, $message);
            }
            if (!in_array($hash, $hashes, true)) {
                $hashes[] = $hash;
                $return[] = $line;
            }
        }
        app('log')->info(sprintf('Went from %d line(s) to %d line(s)', count($array), count($return)));

        return $return;
    }

    /**
     * Do a first sanity check on whatever comes out of the CSV file.
     *
     * @param  array  $line
     *
     * @return array
     */
    private function sanitize(array $line): array
    {
        $lineValues = array_values($line);
        array_walk(
            $lineValues,
            static function ($element) {
                return trim(str_replace('&nbsp;', ' ', (string)$element));
            }
        );

        return $lineValues;
    }
}
