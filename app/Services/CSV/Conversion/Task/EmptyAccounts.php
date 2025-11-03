<?php

/*
 * EmptyAccounts.php
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

namespace App\Services\CSV\Conversion\Task;

use Illuminate\Support\Facades\Log;

/**
 * Class EmptyAccounts
 *
 * A very simple task that makes sure that if the source of a deposit
 * or the destination of a withdrawal is empty, it will be set to "(no name)".
 */
class EmptyAccounts extends AbstractTask
{
    public function process(array $group): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $total = count($group['transactions']);
        foreach ($group['transactions'] as $index => $transaction) {
            Log::debug(sprintf('Now processing transaction %d of %d', $index + 1, $total));
            $group['transactions'][$index] = $this->processTransaction($transaction);
        }

        return $group;
    }

    private function processTransaction(array $transaction): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        if ('withdrawal' === $transaction['type']) {
            $destName = $transaction['destination_name'] ?? '';
            $destId   = (int) ($transaction['destination_id'] ?? 0);
            $destIban = $transaction['destination_iban'] ?? '';
            $destNumber = $transaction['destination_number'] ?? '';
            if ('' === $destName && 0 === $destId && '' === $destIban && 0 === $destNumber) {
                Log::debug('Destination name + ID + IBAN + number of withdrawal are empty, set to "(no name)".');
                $transaction['destination_name'] = '(no name)';
            }
        }
        if ('deposit' === $transaction['type']) {
            $sourceName = $transaction['source_name'] ?? '';
            $sourceId   = (int) ($transaction['source_id'] ?? 0);
            $sourceIban = $transaction['source_iban'] ?? '';
            $sourceNumber = $transaction['source_number'] ?? '';
            if ('' === $sourceName && 0 === $sourceId && '' === $sourceIban && '' === $sourceNumber) {
                Log::debug('Source name + IBAN + ID + number of deposit are empty, set to "(no name)".');
                $transaction['source_name'] = '(no name)';
            }
        }

        return $transaction;
    }

    /**
     * Returns true if the task requires the default account.
     */
    public function requiresDefaultAccount(): bool
    {
        return true;
    }

    /**
     * Returns true if the task requires the primary currency of the user.
     */
    public function requiresTransactionCurrency(): bool
    {
        return false;
    }
}
