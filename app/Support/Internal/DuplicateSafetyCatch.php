<?php declare(strict_types=1);
/*
 * DuplicateSafetyCatch.php
 * Copyright (c) 2023 james@firefly-iii.org
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

namespace App\Support\Internal;

/**
 * Trait DuplicateSafetyCatch
 */
trait DuplicateSafetyCatch
{
    /**
     * @param array  $transaction
     * @param string $originalName
     * @param string $originalIban
     *
     * @return array
     */
    public function positiveTransactionSafetyCatch(array $transaction, string $originalName, string $originalIban): array
    {
        app('log')->debug('Now in positiveTransactionSafetyCatch');
        // safety catch: if the transaction is a transfer, BUT the source and destination are the same, Firefly III will break.
        // The data importer will try to correct this.

        // check for columns:
        if (!array_key_exists('source_id', $transaction) ||
            !array_key_exists('destination_id', $transaction) ||
            !array_key_exists('type', $transaction)) {
            app('log')->debug('positiveTransactionSafetyCatch: missing columns, cannot continue.');
            return $transaction;
        }

        if ('transfer' === $transaction['type'] &&
            0 !== $transaction['destination_id'] &&
            $transaction['destination_id'] === $transaction['source_id']) {
            app('log')->warning('Transaction is a "transfer", but source and destination are the same. Correcting.');
            $transaction['type'] = 'deposit';

            // add error message to transaction:
            $transaction['notes'] = $transaction['notes'] ?? '';
            $transaction['notes'] .= "  \nThe data importer has ignored the following values in the transaction data:\n";
            $transaction['notes'] .= sprintf("- Original source account name: '%s'\n", $originalName);
            $transaction['notes'] .= sprintf("- Original source account IBAN: '%s'\n", $originalIban);
            $transaction['notes'] .= "\nTo learn more, please visit: https://bit.ly/FF3-ignored-values";
            $transaction['notes'] = trim($transaction['notes']);

            unset($transaction['source_id'], $transaction['source_iban'], $transaction['source_number'], $transaction['source_name']);
            $transaction['source_name'] = '(unknown source account)';
            return $transaction;
        }
        app('log')->debug('positiveTransactionSafetyCatch: did not detect a duplicate account.');
        return $transaction;
    }

    /**
     * @param array  $transaction
     * @param string $originalName
     * @param string $originalIban
     *
     * @return array
     */
    public function negativeTransactionSafetyCatch(array $transaction, string $originalName, string $originalIban): array
    {
        app('log')->debug('Now in negativeTransactionSafetyCatch');

        // check for columns:
        if (!array_key_exists('source_id', $transaction) ||
            !array_key_exists('destination_id', $transaction) ||
            !array_key_exists('type', $transaction)) {
            app('log')->debug('negativeTransactionSafetyCatch: missing columns, cannot continue.');
            return $transaction;
        }

        // safety catch: if the transaction is a transfer, BUT the source and destination are the same, Firefly III will break.
        // The data importer will try to correct this.
        if ('transfer' === $transaction['type'] &&
            0 !== $transaction['destination_id'] &&
            $transaction['destination_id'] === $transaction['source_id']) {
            app('log')->warning('Transaction is a "transfer", but source and destination are the same. Correcting.');
            $transaction['type'] = 'withdrawal';

            // add error message to transaction:
            $transaction['notes'] = $transaction['notes'] ?? '';
            $transaction['notes'] .= "  \nThe data importer has ignored the following values in the transaction data:\n";
            $transaction['notes'] .= sprintf("- Original destination account name: '%s'\n", $originalName);
            $transaction['notes'] .= sprintf("- Original destination account IBAN: '%s'\n", $originalIban);
            $transaction['notes'] .= "\nTo learn more, please visit: https://bit.ly/FF3-ignored-values";
            $transaction['notes'] = trim($transaction['notes']);

            unset($transaction['destination_id'], $transaction['destination_iban'], $transaction['destination_number'], $transaction['destination_name']);
            $transaction['destination_name'] = '(unknown destination account)';
        }

        app('log')->debug('negativeTransactionSafetyCatch: did not detect a duplicate account.');
        return $transaction;
    }

}
