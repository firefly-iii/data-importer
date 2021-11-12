<?php
declare(strict_types=1);
/**
 * DeterminesTransactionType.php
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

namespace App\Services\Import;

use Log;

/**
 * Trait DeterminesTransactionType
 */
trait DeterminesTransactionType
{

    /**
     * @param string|null $sourceType
     * @param string|null $destinationType
     *
     * @return string
     */
    protected function determineType(?string $sourceType, ?string $destinationType): string
    {
        Log::debug(sprintf('Now in determineType::determineType("%s", "%s")', $sourceType, $destinationType));
        if (null === $sourceType && null === $destinationType) {
            Log::debug('Return withdrawal, both are NULL');

            return 'withdrawal';
        }
        if ('revenue' === $sourceType) {
            Log::debug('Return deposit, source is a revenue account.');

            return 'deposit';
        }

        // if source is a asset and dest is NULL, its a withdrawal
        if ('asset' === $sourceType && null === $destinationType) {
            Log::debug('Return withdrawal, source is asset');

            return 'withdrawal';
        }
        // if destination is asset and source is NULL, its a deposit
        if (null === $sourceType && 'asset' === $destinationType) {
            Log::debug('Return deposit, destination is asset');

            return 'deposit';
        }

        $key   = sprintf('transaction_types.account_to_transaction.%s.%s', $sourceType, $destinationType);
        $type  = config($key);
        $value = $type ?? 'withdrawal';
        Log::debug(sprintf('Check config for "%s" and found "%s". Returning "%s"', $key, $type, $value));

        return $value;
    }
}
