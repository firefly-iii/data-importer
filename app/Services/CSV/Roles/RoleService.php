<?php

/*
 * RoleService.php
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

namespace App\Services\CSV\Roles;

use App\Services\Camt\Transaction;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Storage\StorageService;
use Genkgo\Camt\Camt053\DTO\Statement as CamtStatement;
use Genkgo\Camt\Config;
use Genkgo\Camt\Reader as CamtReader;
use Illuminate\Support\Facades\Log;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\UnableToProcessCsv;
use InvalidArgumentException;

/**
 * Class RoleService
 */
class RoleService
{
    public const EXAMPLE_COUNT  = 7;
    public const EXAMPLE_LENGTH = 26;

    /**
     * @throws InvalidArgument
     * @throws UnableToProcessCsv
     */
    public static function getColumns(string $content, Configuration $configuration): array
    {
        $reader    = Reader::createFromString($content);

        // configure reader:
        $delimiter = $configuration->getDelimiter();

        switch ($delimiter) {
            default:
            case 'comma':
                $reader->setDelimiter(',');

                break;

            case 'semicolon':
                $reader->setDelimiter(';');

                break;

            case 'tab':
                $reader->setDelimiter("\t");

                break;
        }

        $headers   = [];
        if (true === $configuration->isHeaders()) {
            try {
                $stmt    = new Statement()->limit(1)->offset(0);
                $records = $stmt->process($reader);
                $headers = $records->fetchOne();
                // @codeCoverageIgnoreStart
            } catch (Exception $e) {
                Log::error(sprintf('[%s]: %s',config('importer.version'), $e->getMessage()));

                throw new InvalidArgumentException($e->getMessage());
            }
            // @codeCoverageIgnoreEnd
            Log::debug('Detected file headers:', $headers);
        }
        if (false === $configuration->isHeaders()) {
            Log::debug('Role service: file has no headers');

            try {
                $stmt    = new Statement()->limit(1)->offset(0);
                $records = $stmt->process($reader);
                $count   = count($records->fetchOne());
                Log::debug(sprintf('Role service: first row has %d columns', $count));
                for ($i = 0; $i < $count; ++$i) {
                    $headers[] = sprintf('Column #%d', $i + 1);
                }
                // @codeCoverageIgnoreStart
            } catch (Exception $e) {
                Log::error(sprintf('[%s]: %s',config('importer.version'), $e->getMessage()));

                throw new InvalidArgumentException($e->getMessage());
            }
        }

        return $headers;
    }

    /**
     * @throws Exception
     */
    public static function getExampleData(string $content, Configuration $configuration): array
    {
        $reader    = Reader::createFromString($content);

        // configure reader:
        $delimiter = $configuration->getDelimiter();

        switch ($delimiter) {
            default:
            case 'comma':
                $reader->setDelimiter(',');

                break;

            case 'semicolon':
                $reader->setDelimiter(';');

                break;

            case 'tab':
                $reader->setDelimiter("\t");

                break;
        }

        $offset    = $configuration->isHeaders() ? 1 : 0;
        $examples  = [];

        // make statement.
        try {
            $stmt = new Statement()->limit(self::EXAMPLE_COUNT)->offset($offset);
            // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            Log::error(sprintf('[%s]: %s',config('importer.version'), $e->getMessage()));

            throw new InvalidArgumentException($e->getMessage());
        }

        /** @codeCoverageIgnoreEnd */

        // grab the records:
        $records   = $stmt->process($reader);

        /** @var array $line */
        foreach ($records as $line) {
            $line = array_values($line);
            // $line = SpecificService::runSpecifics($line, $configuration->getSpecifics());
            foreach ($line as $index => $cell) {
                if (strlen((string) $cell) > self::EXAMPLE_LENGTH) {
                    $cell = sprintf('%s...', substr((string) $cell, 0, self::EXAMPLE_LENGTH));
                }
                $examples[$index][] = $cell;
                $examples[$index]   = array_unique($examples[$index]);
            }
        }
        foreach ($examples as $line => $entries) {
            asort($entries);
            $examples[$line] = $entries;
        }

        return $examples;
    }

    public static function getExampleDataFromCamt(string $content, Configuration $configuration): array
    {
        $camtReader   = new CamtReader(Config::getDefault());
        $camtMessage  = $camtReader->readString(StorageService::getContent(session()->get(Constants::UPLOAD_DATA_FILE))); // -> Level A
        $transactions = [];
        $examples     = [];
        $fieldNames   = array_keys(config('camt.fields'));
        foreach ($fieldNames as $name) {
            $examples[$name] = [];
        }

        /**
         * This code creates separate Transaction objects for transaction details,
         * even when the user indicates these details should be splits or ignored entirely.
         * This is because we still need to extract possible example data from these transaction details.
         */
        $statements   = $camtMessage->getRecords();

        /** @var CamtStatement $statement */
        foreach ($statements as $statement) { // -> Level B
            $entries = $statement->getEntries();
            foreach ($entries as $entry) {                       // -> Level C
                $count = count($entry->getTransactionDetails()); // count level D entries.
                if (0 === $count) {
                    // TODO Create a single transaction, I guess?
                    $transactions[] = new Transaction($configuration, $camtMessage, $statement, $entry, []);
                }
                if (0 !== $count) {
                    foreach ($entry->getTransactionDetails() as $detail) {
                        $transactions[] = new Transaction($configuration, $camtMessage, $statement, $entry, [$detail]);
                    }
                }
            }
        }
        $count        = 0;

        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            if (15 === $count) { // do not check more than 15 transactions to fill the example-data
                break;
            }
            foreach ($fieldNames as $name) {
                if (array_key_exists($name, $examples)) { // there is at least one example, so we can check how many
                    if (count($examples[$name]) > 5) { // there are already five examples, so jump to next field
                        continue;
                    }
                } // otherwise, try to fetch data
                $splits = $transaction->countSplits();
                if (0 === $splits) {
                    $value = $transaction->getFieldByIndex($name, 0);
                    if ('' !== $value) {
                        $examples[$name][] = $value;
                    }
                }
                if ($splits > 0) {
                    for ($index = 0; $index < $splits; ++$index) {
                        $value = $transaction->getFieldByIndex($name, $index);
                        if ('' !== $value) {
                            $examples[$name][] = $value;
                        }
                    }
                }
            }
            ++$count;
        }
        foreach ($examples as $key => $list) {
            $examples[$key] = array_unique($list);
            $examples[$key] = array_filter($examples[$key], fn (string $value) => '' !== $value);
        }

        return $examples;
    }
}
