<?php

/*
 * LineProcessor.php
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
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use Illuminate\Support\Facades\Log;

/**
 * Class LineProcessor
 *
 * Processes single lines from a CSV file. Converts them into
 * arrays with single "ColumnValue" that hold the value + the role of the column
 * + the mapped value (if any).
 */
class LineProcessor
{
    use ProgressInformation;

    private Configuration $configuration;
    private string $dateFormat;
    private array  $doMapping;
    private array  $mappedValues;
    private array  $mapping;
    private array  $roles;

    /**
     * LineProcessor constructor.
     */
    public function __construct(Configuration $configuration)
    {
        Log::debug('Created LineProcessor()');
        Log::debug('Roles', $configuration->getRoles());
        Log::debug('Mapping (will not be printed)');
        $this->configuration = $configuration;
        $this->roles      = $configuration->getRoles();
        $this->mapping    = $configuration->getMapping();
        $this->doMapping  = $configuration->getDoMapping();
        $this->dateFormat = $configuration->getDate();
    }

    public function processCSVLines(array $lines): array
    {
        $processed = [];
        $count     = count($lines);

        Log::info(sprintf('Now processing the data in the %d CSV lines...', $count));

        foreach ($lines as $index => $line) {
            Log::debug(sprintf('Now processing CSV line #%d/#%d', $index + 1, $count));

            try {
                $processed[] = $this->process($line);
            } catch (ImporterErrorException $e) {
                Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
                //                Log::error($e->getTraceAsString());
                $this->addError(0, $e->getMessage());
            }
        }

        Log::info(sprintf('Done processing data in %d CSV lines...', $count));

        return $processed;
    }

    /**
     * Convert each raw CSV to a set of ColumnValue objects, which hold as much info
     * as we can cram into it. These new lines can be imported later on.
     *
     * @throws ImporterErrorException
     */
    private function process(array $line): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $count       = count($line);
        $return      = [];
        foreach ($line as $columnIndex => $value) {
            Log::debug(sprintf('Now at column %d/%d', $columnIndex + 1, $count));
            $value        = trim((string)$value);
            $originalRole = $this->roles[$columnIndex] ?? '_ignore';
            Log::debug(sprintf('Now at column #%d (%s), value "%s"', $columnIndex + 1, $originalRole, $value));
            if ('_ignore' === $originalRole) {
                Log::debug(sprintf('Ignore column #%d because role is "_ignore".', $columnIndex + 1));

                continue;
            }
            if ('' === $value) {
                Log::debug(sprintf('Ignore column #%d because value is "".', $columnIndex + 1));

                continue;
            }

            // is a mapped value present?
            $mapped       = $this->mapping[$columnIndex][$value] ?? 0;
            Log::debug(sprintf('ColumnIndex is %s', var_export($columnIndex, true)));
            Log::debug(sprintf('Value is %s', var_export($value, true)));
            // Log::debug('Local mapping (will not be printed)');
            // the role might change because of the mapping.
            $role         = $this->getRoleForColumn($columnIndex, $mapped);
            $appendValue  = config(sprintf('csv.import_roles.%s.append_value', $originalRole));

            if (null === $appendValue) {
                $appendValue = false;
            }

            // Log::debug(sprintf('Append value config: %s', sprintf('csv.import_roles.%s.append_value', $originalRole)));

            $columnValue  = new ColumnValue();
            $columnValue->setValue($value);
            $columnValue->setRole($role);
            $columnValue->setAppendValue($appendValue);
            $columnValue->setMappedValue($mapped);
            $columnValue->setOriginalRole($originalRole);

            // if column role is 'date', add the date config for conversion:
            if (in_array($originalRole, ['date_transaction', 'date_interest', 'date_due', 'date_payment', 'date_process', 'date_book', 'date_invoice'], true)) {
                Log::debug(sprintf('Because role is %s, set date format to "%s" (via setConfiguration).', $originalRole, $this->dateFormat));
                $columnValue->setConfiguration($this->dateFormat);
            }

            $return[]     = $columnValue;
        }

        // Process pseudo identifier if it exists
        if ($this->configuration->hasPseudoIdentifier()) {
            Log::debug('Processing pseudo identifier...');
            $pseudoIdentifier = $this->configuration->getPseudoIdentifier();

            // Combine values from source columns
            $combinedParts = [];
            foreach ($pseudoIdentifier['source_columns'] as $sourceIndex) {
                $value = isset($line[$sourceIndex]) ? trim((string)$line[$sourceIndex]) : '';
                if ('' !== $value) {
                    $combinedParts[] = $value;
                }
            }

            // Only create pseudo identifier if we have values
            if (count($combinedParts) > 0) {
                $separator = $pseudoIdentifier['separator'];
                $combinedValue = implode($separator, $combinedParts);

                // Hash composite identifiers (multiple columns) to avoid long values
                if (count($pseudoIdentifier['source_columns']) > 1) {
                    $combinedValue = substr(hash('sha256', $combinedValue), 0, 8);
                }

                $pseudoIdentifierValue = new ColumnValue();
                $pseudoIdentifierValue->setValue($combinedValue);
                $pseudoIdentifierValue->setRole($pseudoIdentifier['role']);
                $pseudoIdentifierValue->setOriginalRole($pseudoIdentifier['role']);
                $pseudoIdentifierValue->setMappedValue(0);
                $pseudoIdentifierValue->setAppendValue(false);

                $return[] = $pseudoIdentifierValue;
                Log::debug(sprintf('Added pseudo identifier with value: %s', $combinedValue));
            }
        }

        // add a special column value for the "source"
        $columnValue = new ColumnValue();
        $columnValue->setValue(sprintf('jc5-data-import-v%s', config('importer.version')));
        $columnValue->setMappedValue(0);
        $columnValue->setAppendValue(false);
        $columnValue->setRole('original-source');
        $return[]    = $columnValue;
        Log::debug(sprintf('Added column #%d to denote the original source.', count($return)));

        return $return;
    }

    /**
     * If the value in the column is mapped to a certain ID,
     * the column where this ID must be placed will change.
     *
     * For example, if you map role "budget-name" with value "groceries" to 1,
     * then that should become the budget-id. Not the name.
     *
     * @throws ImporterErrorException
     */
    private function getRoleForColumn(int $column, int $mapped): string
    {
        $role                           = $this->roles[$column] ?? '_ignore';
        if (0 === $mapped) {
            Log::debug(sprintf('Column #%d with role "%s" is not mapped.', $column + 1, $role));

            return $role;
        }
        if (!(array_key_exists($column, $this->doMapping) && true === $this->doMapping[$column])) {
            // if the mapping has been filled in already by a role with a higher priority,
            // ignore the mapping.
            Log::debug(sprintf('Column #%d ("%s") has something already.', $column, $role));

            return $role;
        }
        $roleMapping                    = [
            'account-id'            => 'account-id',
            'account-name'          => 'account-id',
            'account-iban'          => 'account-id',
            'account-number'        => 'account-id',
            'bill-id'               => 'bill-id',
            'bill-name'             => 'bill-id',
            'budget-id'             => 'budget-id',
            'budget-name'           => 'budget-id',
            'currency-id'           => 'currency-id',
            'currency-name'         => 'currency-id',
            'currency-code'         => 'currency-id',
            'currency-symbol'       => 'currency-id',
            'category-id'           => 'category-id',
            'category-name'         => 'category-id',
            'foreign-currency-id'   => 'foreign-currency-id',
            'foreign-currency-code' => 'foreign-currency-id',
            'opposing-id'           => 'opposing-id',
            'opposing-name'         => 'opposing-id',
            'opposing-iban'         => 'opposing-id',
            'opposing-number'       => 'opposing-id',
        ];
        if (!array_key_exists($role, $roleMapping)) {
            throw new ImporterErrorException(sprintf('Cannot indicate new role for mapped role "%s"', $role)); // @codeCoverageIgnore
        }
        $newRole                        = $roleMapping[$role];
        if ($newRole !== $role) {
            Log::debug(sprintf('Role was "%s", but because of mapping (mapped to #%d), role becomes "%s"', $role, $mapped, $newRole));
        }

        // also store the $mapped values in a "mappedValues" array.
        // used to validate whatever has been set as mapping
        $this->mappedValues[$newRole][] = $mapped;
        $this->mappedValues[$newRole]   = array_unique($this->mappedValues[$newRole]);
        Log::debug(sprintf('Values mapped to role "%s" are: ', $newRole), $this->mappedValues[$newRole]);

        return $newRole;
    }
}
