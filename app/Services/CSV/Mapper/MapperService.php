<?php
/*
 * MapperService.php
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

namespace App\Services\CSV\Mapper;

use App\Exceptions\ImporterErrorException;
use App\Services\Camt\Transaction;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Storage\StorageService;
use Genkgo\Camt\Camt053\DTO\Statement as CamtStatement;
use Genkgo\Camt\Config;
use Genkgo\Camt\DTO\Entry;
use Genkgo\Camt\Reader as CamtReader;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Statement;

/**
 * Class MapperService
 */
class MapperService
{
    /**
     * Appends the given array with data from the CSV file in the config.
     * TODO remove reference to specifics.
     *
     * @param string $content
     * @param string $delimiter
     * @param bool   $hasHeaders
     * @param array  $specifics
     * @param array  $data
     *
     * @return array
     * @throws ImporterErrorException
     */
    public static function getMapData(string $content, string $delimiter, bool $hasHeaders, array $specifics, array $data): array
    {
        app('log')->debug('Now in getMapData');
        // make file reader first.
        $reader = Reader::createFromString($content);

        // reader not configured to use correct delimiter.
        try {
            $reader->setDelimiter($delimiter);
        } catch (Exception $e) {
            app('log')->error($e->getMessage());
            //            app('log')->error($e->getTraceAsString());
            throw new ImporterErrorException(sprintf('Could not set delimiter: %s', $e->getMessage()));
        }

        $offset = 0;
        if (true === $hasHeaders) {
            $offset = 1;
        }
        try {
            $stmt    = (new Statement())->offset($offset);
            $records = $stmt->process($reader);
        } catch (Exception $e) {
            app('log')->error($e->getMessage());
            throw new ImporterErrorException($e->getMessage());
        }
        // loop each row, apply specific:
        app('log')->debug('Going to loop all records to collect information');
        foreach ($records as $row) {
            //$row = SpecificService::runSpecifics($row, $specifics);
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

    /**
     * Appends the given array with data from the CAMT file in the config.
     *
     * @param string $content
     * @param string $delimiter
     * @param bool   $hasHeaders
     * @param array  $specifics
     * @param array  $data
     *
     * @return array
     * @throws ImporterErrorException
     */
    public static function getMapDataForCamt(Configuration $configuration, string $content, array $data): array
    {
        app('log')->debug('Now in getMapDataForCamt');

        // make file reader first.
        $camtReader   = new CamtReader(Config::getDefault());
        $camtMessage  = $camtReader->readString($content);
        $transactions = [];


        // loop over records.
        $statements   = $camtMessage->getRecords();
        /** @var CamtStatement $statement */
        foreach ($statements as $statement) { // -> Level B
            $entries = $statement->getEntries();
            /** @var Entry $entry */
            foreach ($entries as $entry) { // -> Level C
                $count = count($entry->getTransactionDetails()); // count level D entries.
                if (0 === $count) {
                    // TODO Create a single transaction, I guess?
                    $transactions[] = new Transaction($configuration, $camtMessage, $statement, $entry);
                }
                if (0 !== $count) {
                    foreach ($entry->getTransactionDetails() as $detail) {
                        $transactions[] = new Transaction($configuration, $camtMessage, $statement, $entry, $detail);
                    }
                }
            }
        }
        $mappableFields = self::getMappableFieldsForCamt();
        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            // take all mappable fields from this transaction, and add to $values in the data thing
            foreach(array_keys($mappableFields) as $title) {
                $data[$title]['values'][] = $transaction->getField($title);
            }
        }
        // make all values unique for mapping and remove empty vars.
        foreach($data as $title => $info) {
            $filtered = array_filter(
                $info['values'],
                static function (string $value) {
                    return '' !== $value;
                }
            );
            $info['values'] = array_unique($filtered);
            sort($info['values']);
            $data[$title]['values'] = $info['values'];

        }
        return $data;
    }

    /**
     * @return array
     */
    private static function getMappableFieldsForCamt(): array
    {
        $fields = config('camt.fields');
        $return = [];
        /** @var array $field */
        foreach($fields as $name => $field) {
            if($field['mappable']) {
                $return[$name] = $field;
            }
        }
        return $return;
    }
}
