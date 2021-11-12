<?php
declare(strict_types=1);
/**
 * MapperService.php
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

namespace App\Services\CSV\Mapper;


use App\Exceptions\ImportException;
use App\Services\CSV\Specifics\SpecificService;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Statement;
use Log;

/**
 * Class MapperService
 */
class MapperService
{

    /**
     * Appends the given array with data from the CSV file in the config.
     *
     * @param string $content
     * @param string $delimiter
     * @param bool   $hasHeaders
     * @param array  $specifics
     * @param array  $data
     *
     * @return array
     * @throws ImportException
     */
    public static function getMapData(string $content, string $delimiter, bool $hasHeaders, array $specifics, array $data): array
    {
        Log::debug('Now in getMapData');
        // make file reader first.
        $reader = Reader::createFromString($content);

        // reader not configured to use correct delimiter.
        try {
            $reader->setDelimiter($delimiter);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            throw new ImportException(sprintf('Could not set delimiter: %s', $e->getMessage()));
        }

        $offset = 0;
        if (true === $hasHeaders) {
            $offset = 1;
        }
        try {
            $stmt    = (new Statement)->offset($offset);
            $records = $stmt->process($reader);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw new ImportException($e->getMessage());
        }
        // loop each row, apply specific:
        Log::debug('Going to loop all records to collect information');
        foreach ($records as $row) {
            $row = SpecificService::runSpecifics($row, $specifics);
            // loop each column, put in $data
            foreach ($row as $columnIndex => $column) {
                if (!isset($data[$columnIndex])) {
                    // don't need to handle this. Continue.
                    continue;
                }
                if ('' !== $column) {
                    $data[$columnIndex]['values'][] = trim($column);
                }
            }
        }
        // loop data, clean up data:
        foreach ($data as $index => $columnInfo) {
            $data[$index]['values'] = array_unique($columnInfo['values']);
            sort($data[$index]['values']);

            /*
             * The config may contain mapped values that aren't in this CSV file. They're saved as
             * hidden values (under unused_maps).
             *
             * Change on 2021-10-10: These unused_maps are no longer saved or use.
             * The original mapping (saved on disk) will be merged with the new mapping (submitted by the user)
            */

//            $mappedValues  = array_keys($columnInfo['mapped'] ?? []);
//            $foundValues   = $columnInfo['values'] ?? [];
//            $missingValues = array_diff($mappedValues, $foundValues);
//            // get them from mapped.
//            $missingMap = [];
//            foreach ($missingValues as $missingValue) {
//                if (array_key_exists($missingValue, $columnInfo['mapped'])) {
//                    $missingMap[$missingValue] = $columnInfo['mapped'][$missingValue];
//                }
//            }
//            $data[$index]['missing_map'] = $missingMap;
        }

        return $data;
    }

}
