<?php

/*
 * ColumnValueConverter.php
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

use UnexpectedValueException;
use JsonException;
use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use Illuminate\Support\Facades\Log;

/**
 * Class ColumnValueConverter
 *
 * Converts rows of ColumnValue's to pseudo transactions.
 * Pseudo because they still require some lookups and cleaning up.
 */
class ColumnValueConverter
{
    use ProgressInformation;
    private array         $roleToTransaction;

    /**
     * ColumnValueConverter constructor.
     */
    public function __construct(private Configuration $configuration)
    {
        $this->roleToTransaction = config('csv.role_to_transaction');
    }

    /**
     * @throws ImporterErrorException
     */
    public function processValueArrays(array $lines): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));

        $processed = [];
        $count     = count($lines);
        Log::info(sprintf('Now parsing and combining %d lines.', $count));
        foreach ($lines as $index => $line) {
            Log::debug(sprintf('Now processing line %d/%d', $index + 1, $count));
            $processed[] = $this->processValueArray($line);
        }
        Log::info(sprintf('Done parsing and combining %d lines.', $count));

        return $processed;
    }

    /**
     * @throws ImporterErrorException
     */
    private function processValueArray(array $line): array
    {
        $count       = count($line);
        Log::debug(sprintf('Now in %s with %d columns in this line.', __METHOD__, $count));
        // make a new transaction:
        $transaction = [
            // 'user'          => 1, // ??
            'group_title'             => null,
            'error_if_duplicate_hash' => $this->configuration->isIgnoreDuplicateTransactions(),
            'transactions'            => [
                [
                    'type'             => 'withdrawal',
                    'date'             => '',
                    'currency_id'      => null,
                    'currency_code'    => null,
                    'amount'           => null,
                    'amount_modifier'  => '1', // 1 or -1
                    'description'      => null,
                    'source_id'        => null,
                    'source_name'      => null,
                    'destination_id'   => null,
                    'destination_name' => null,
                    'tags_comma'       => [],
                    'tags_space'       => [],

                    // extra fields for amounts:
                    'amount_debit'     => null,
                    'amount_credit'    => null,
                    'amount_negated'   => null,
                ],
            ],
        ];

        /**
         * @var int         $columnIndex
         * @var ColumnValue $value
         */
        foreach ($line as $columnIndex => $value) {
            $role             = $value->getRole();
            $transactionField = $this->roleToTransaction[$role] ?? null;
            $parsedValue      = $value->getParsedValue();
            if (null === $transactionField) {
                throw new UnexpectedValueException(sprintf('No place for role "%s"', $value->getRole()));
            }
            if (null === $parsedValue) {
                Log::debug(sprintf('Skip column #%d with role "%s" (in field "%s")', $columnIndex + 1, $role, $transactionField));

                continue;
            }
            Log::debug(
                sprintf(
                    'Stored column #%d with value "%s" and role "%s" in field "%s"',
                    $columnIndex + 1,
                    $this->toString($parsedValue),
                    $role,
                    $transactionField
                )
            );

            // if append, append.
            if (true === $value->isAppendValue()) {
                Log::debug(
                    sprintf('Column #%d with role "%s" (in field "%s") must be appended to the previous value.', $columnIndex + 1, $role, $transactionField),
                    [$parsedValue]
                );
                if (is_array($parsedValue)) {
                    $transaction['transactions'][0][$transactionField] ??= [];
                    $transaction['transactions'][0][$transactionField] = array_merge($transaction['transactions'][0][$transactionField], $parsedValue);
                    Log::debug(
                        sprintf('Value for [transactions][#0][%s] is now ', $transactionField),
                        $transaction['transactions'][0][$transactionField]
                    );
                }
                if (!is_array($parsedValue)) {
                    $transaction['transactions'][0][$transactionField] ??= '';
                    $transaction['transactions'][0][$transactionField] = trim(
                        sprintf('%s %s', $transaction['transactions'][0][$transactionField], $parsedValue)
                    );
                }
            }
            // if not, not.
            if (false === $value->isAppendValue()) {
                Log::debug(
                    sprintf('Column #%d with role "%s" (in field "%s") must NOT be appended to the previous value.', $columnIndex + 1, $role, $transactionField)
                );
                $transaction['transactions'][0][$transactionField] = $parsedValue;
            }
            // if this is an account field, AND the column is mapped, store the original value just in case.
            $saveRoles        = ['account-name', 'opposing-name', 'account-iban', 'opposing-iban', 'account-number', 'opposing-number'];
            if (0 !== $value->getMappedValue() && in_array($value->getOriginalRole(), $saveRoles, true)) {
                Log::debug(
                    sprintf(
                        'The original value ("%s") in column "%s" (originally stored in "%s") was saved just in case.',
                        $value->getValue(),
                        $value->getRole(),
                        $value->getOriginalRole()
                    )
                );
                $transaction['transactions'][0][sprintf('original-%s', $value->getOriginalRole())] = $value->getValue();
            }
        }
        Log::debug('Almost final transaction', $transaction);

        return $transaction;
    }

    /**
     * @param mixed $value
     *
     * @throws ImporterErrorException
     */
    private function toString($value): string
    {
        if (is_array($value)) {
            try {
                return json_encode($value, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new ImporterErrorException($e->getMessage(), 0, $e);
            }
        }

        return (string) $value;
    }
}
