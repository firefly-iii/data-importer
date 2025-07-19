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
use App\Services\Shared\Configuration\Configuration;
use Genkgo\Camt\Camt053\DTO\Statement as CamtStatement;
use Genkgo\Camt\Config;
use Genkgo\Camt\Reader as CamtReader;
use Illuminate\Support\Facades\Log;
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
     * @throws ImporterErrorException
     */
    public static function getMapData(string $content, string $delimiter, bool $hasHeaders, array $specifics, array $data): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        // make file reader first.
        $reader = Reader::createFromString($content);

        // reader not configured to use correct delimiter.
        try {
            $reader->setDelimiter($delimiter);
        } catch (Exception $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

            //            Log::error($e->getTraceAsString());
            throw new ImporterErrorException(sprintf('Could not set delimiter: %s', $e->getMessage()));
        }

        $offset = 0;
        if (true === $hasHeaders) {
            $offset = 1;
        }

        try {
            $stmt    = new Statement()->offset($offset);
            $records = $stmt->process($reader);
        } catch (Exception $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

            throw new ImporterErrorException($e->getMessage());
        }
        // loop each row, apply specific:
        Log::debug('Going to loop all records to collect information');
        foreach ($records as $row) {
            // $row = SpecificService::runSpecifics($row, $specifics);
            // loop each column, put in $data
            foreach ($row as $columnIndex => $column) {
                if (!array_key_exists($columnIndex, $data)) {
                    // don't need to handle this. Continue.
                    continue;
                }
                if ('' !== $column) {
                    $data[$columnIndex]['values'][] = trim((string) $column);
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
     * @throws ImporterErrorException
     */
    public static function getMapDataForCamt(Configuration $configuration, string $content, array $data): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        // make file reader first.
        $camtReader     = new CamtReader(Config::getDefault());
        $camtMessage    = $camtReader->readString($content);
        $transactions   = [];

        // loop over records.
        $statements     = $camtMessage->getRecords();

        /** @var CamtStatement $statement */
        foreach ($statements as $statement) { // -> Level B
            $entries = $statement->getEntries();
            foreach ($entries as $entry) {                       // -> Level C
                $count = count($entry->getTransactionDetails()); // count level D entries.
                if (0 === $count) {
                    // TODO Create a single transaction, I guess?
                    $transactions[] = new Transaction($camtMessage, $statement, $entry, []);
                }
                if (0 !== $count) {
                    // create separate transactions, no matter user pref.
                    foreach ($entry->getTransactionDetails() as $detail) {
                        $transactions[] = new Transaction($camtMessage, $statement, $entry, [$detail]);
                    }
                }
            }
        }
        $mappableFields = self::getMappableFieldsForCamt();

        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            // take all mappable fields from this transaction, and add to $values in the data thing

            $splits = $transaction->countSplits();

            foreach (array_keys($mappableFields) as $title) {
                if (!array_key_exists($title, $data)) {
                    continue;
                }
                if (0 === $splits) {
                    continue;
                }
                for ($index = 0; $index < $splits; ++$index) {
                    $value = $transaction->getFieldByIndex($title, $index);
                    if ('' !== $value) {
                        $data[$title]['values'][] = $value;
                    }
                }
            }
        }
        // make all values unique for mapping and remove empty vars.
        foreach ($data as $title => $info) {
            $filtered               = array_filter(
                $info['values'],
                static fn (string $value) => '' !== $value
            );
            $info['values']         = array_unique($filtered);
            sort($info['values']);
            $data[$title]['values'] = $info['values'];
        }
        // unset entries with zero values.
        foreach ($data as $title => $info) {
            if (0 === count($info['values'])) {
                unset($data[$title]);
            }
        }

        return $data;
    }

    private static function getMappableFieldsForCamt(): array
    {
        $fields = config('camt.fields');

        return array_filter($fields, function (array $field) {
            return $field['mappable'];
        });
    }
}
