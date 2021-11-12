<?php
declare(strict_types=1);
/**
 * Accounts.php
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

namespace App\Services\Import\Task;

use Log;

/**
 * Class EmptyAccounts
 *
 * A very simple task that makes sure that if the source of a deposit
 * or the destination of a withdrawal is empty, it will be set to "(no name)".
 */
class EmptyAccounts extends AbstractTask
{
    /**
     * @param array $group
     *
     * @return array
     */
    public function process(array $group): array
    {
        Log::debug('Now in EmptyAccounts::process()');
        $total = count($group['transactions']);
        foreach ($group['transactions'] as $index => $transaction) {
            Log::debug(sprintf('Now processing transaction %d of %d', $index + 1, $total));
            $group['transactions'][$index] = $this->processTransaction($transaction);
        }

        return $group;
    }

    /**
     * @param array $transaction
     *
     * @return array
     */
    private function processTransaction(array $transaction): array
    {
        Log::debug('Now in EmptyAccounts::processTransaction()');

        if ('withdrawal' === $transaction['type']) {
            $destName = $transaction['destination_name'] ?? '';
            $destId   = (int) ($transaction['destination_id'] ?? 0);
            $destIban = $transaction['destination_iban'] ?? '';
            if ('' === $destName && 0 === $destId && '' === $destIban) {
                Log::debug('Destination name + ID + IBAN of withdrawal are empty, set to "(no name)".');
                $transaction['destination_name'] = '(no name)';
            }
        }
        if ('deposit' === $transaction['type']) {
            $sourceName = $transaction['source_name'] ?? '';
            $sourceId   = (int) ($transaction['source_id'] ?? 0);
            $sourceIban = $transaction['source_iban'] ?? '';
            if ('' === $sourceName && 0 === $sourceId && '' === $sourceIban) {
                Log::debug('Source name + IBAN + ID of deposit are empty, set to "(no name)".');
                $transaction['source_name'] = '(no name)';
            }
        }

        return $transaction;
    }

    /**
     * Returns true if the task requires the default account.
     *
     * @return bool
     */
    public function requiresDefaultAccount(): bool
    {
        return true;
    }

    /**
     * Returns true if the task requires the default currency of the user.
     *
     * @return bool
     */
    public function requiresTransactionCurrency(): bool
    {
        return false;
    }

}
