<?php

/*
 * RoleService.php
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

namespace App\Services\CSV\Roles;

use App\Services\Camt\TransactionFactory;
use App\Services\Shared\Configuration\Configuration;
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
    public const int EXAMPLE_COUNT  = 7;
    public const int EXAMPLE_LENGTH = 26;

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
                $headers = $records->first();
                // @codeCoverageIgnoreStart
            } catch (Exception $e) {
                Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

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
                $count   = count($records->first());
                Log::debug(sprintf('Role service: first row has %d columns', $count));
                for ($i = 0; $i < $count; ++$i) {
                    $headers[] = sprintf('Column #%d', $i + 1);
                }
                // @codeCoverageIgnoreStart
            } catch (Exception $e) {
                Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

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
        $reader         = Reader::createFromString($content);

        // configure reader:
        $delimiter      = $configuration->getDelimiter();

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

        $offset         = $configuration->isHeaders() ? 1 : 0;
        $examples       = [];
        $pseudoExamples = [];

        // make statement.
        try {
            $stmt = new Statement()->limit(self::EXAMPLE_COUNT)->offset($offset);
            // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

            throw new InvalidArgumentException($e->getMessage());
        }

        /** @codeCoverageIgnoreEnd */

        // grab the records:
        $records        = $stmt->process($reader);

        /** @var array $line */
        foreach ($records as $line) {
            $line = array_values($line);
            // $line = SpecificService::runSpecifics($line, $configuration->getSpecifics());

            // Generate pseudo identifier example from this line BEFORE deduplication
            if ($configuration->hasPseudoIdentifier()) {
                $pseudoIdentifier = $configuration->getPseudoIdentifier();
                $combinedParts    = [];

                foreach ($pseudoIdentifier['source_columns'] as $sourceIndex) {
                    $value = isset($line[$sourceIndex]) ? trim((string)$line[$sourceIndex]) : '';
                    if ('' !== $value) {
                        $combinedParts[] = $value;
                    }
                }

                if (count($combinedParts) > 0) {
                    $separator     = $pseudoIdentifier['separator'];
                    $combinedValue = implode($separator, $combinedParts);
                    $rawValue      = $combinedValue;

                    // Hash composite identifiers (multiple columns) to match actual processing
                    $count         = count($pseudoIdentifier['source_columns']);
                    if ($count > 1) {
                        $combinedValue    = substr(hash('sha256', $combinedValue), 0, 8);
                        $pseudoExamples[] = ['raw' => $rawValue, 'hashed' => $combinedValue];
                    }
                    if ($count <= 1) {
                        $pseudoExamples[] = ['raw' => $rawValue, 'hashed' => null];
                    }
                }
            }

            foreach ($line as $index => $cell) {
                if (strlen((string) $cell) > self::EXAMPLE_LENGTH) {
                    $cell = sprintf('%s...', substr((string) $cell, 0, self::EXAMPLE_LENGTH));
                }
                $examples[$index][] = $cell;
                $examples[$index]   = array_unique($examples[$index]);
            }
        }

        // Deduplicate pseudo identifier examples
        if (count($pseudoExamples) > 0) {
            $pseudoExamples = array_values(array_unique($pseudoExamples, SORT_REGULAR));
        }

        foreach ($examples as $line => $entries) {
            asort($entries);
            $examples[$line] = $entries;
        }

        return [
            'columns'           => $examples,
            'pseudo_identifier' => $pseudoExamples,
        ];
    }

    public static function getExampleDataFromCamt(string $content, Configuration $configuration): array
    {
        $camtReader   = new CamtReader(Config::getDefault());
        $camtMessage  = $camtReader->readString($content); // -> Level A
        $camtType     = $configuration->getCamtType();
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
                    $transactions[] = TransactionFactory::create($camtType, $camtMessage, $statement, $entry, []);
                }
                if (0 !== $count) {
                    foreach ($entry->getTransactionDetails() as $detail) {
                        $transactions[] = TransactionFactory::create($camtType, $camtMessage, $statement, $entry, [$detail]);
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
                $name   = (string)$name;
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
            // filter disabled, since empty values are already removed.
            // $examples[$key] = array_filter($examples[$key], fn (string $value) => '' !== $value);
        }

        return $examples;
    }
}
