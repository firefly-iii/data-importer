<?php
declare(strict_types=1);
/**
 * Tags.php
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


/**
 * Class Tags
 */
class Tags extends AbstractTask
{

    /**
     * @param array $group
     *
     * @return array
     */
    public function process(array $group): array
    {
        foreach ($group['transactions'] as $index => $transaction) {
            $group['transactions'][$index] = $this->processTags($transaction);
        }

        return $group;
    }

    /**
     * Do something with the collected tags.
     *
     * @param array $transaction
     *
     * @return array
     */
    private function processTags(array $transaction): array
    {
        $transaction['tags'] = array_unique(array_merge(array_values($transaction['tags_space']), array_values($transaction['tags_comma'])));
        unset($transaction['tags_comma'], $transaction['tags_space']);

        return $transaction;
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

    /**
     * Returns true if the task requires the default account.
     *
     * @return bool
     */
    public function requiresDefaultAccount(): bool
    {
        return false;
    }
}
