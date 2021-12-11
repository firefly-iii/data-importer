<?php
/**
 * FilterTransactions.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III Spectre importer
 * (https://github.com/firefly-iii/spectre-importer).
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


namespace App\Services\Spectre\Conversion\Routine;


use App\Services\Shared\Conversion\ProgressInformation;

/**
 * Class FilterTransactions
 */
class FilterTransactions
{
    use ProgressInformation;

    /**
     * FilterTransactions constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param array $transactions
     *
     * @return array
     */
    public function filter(array $transactions): array
    {
        $start  = count($transactions);
        $return = [];
        /** @var array $transaction */
        foreach ($transactions as $transaction) {

            unset($transaction['transactions'][0]['datetime']);

            if (0 === (int) ($transaction['transactions'][0]['category_id'] ?? 0)) {
                //app('log')->debug('IS NULL');
                unset($transaction['transactions'][0]['category_id']);
            }
            $return[] = $transaction;
            // app('log')->debug('Filtered ', $transaction);
        }
        $end = count($return);
        $this->addMessage(0, sprintf('Filtered down from %d (possibly duplicate) entries to %d unique transactions.', $start, $end));

        return $return;
    }

}
