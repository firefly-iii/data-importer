<?php

/*
 * PositiveAmount.php
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

use App\Services\CSV\Converter\Amount as AmountConverter;

/**
 * Class PositiveAmount
 */
class PositiveAmount extends AbstractTask
{
    /**
     * Make sure amount is always positive when submitting.
     *
     * {@inheritDoc}
     */
    public function process(array $group): array
    {
        foreach ($group['transactions'] as $index => $transaction) {
            $group['transactions'][$index]['amount'] ??= '0';
            $group['transactions'][$index]['amount'] = AmountConverter::positive($group['transactions'][$index]['amount']);

            // also make foreign amount positive:
            if (array_key_exists('foreign_amount', $group['transactions'][$index])) {
                if ('' !== $group['transactions'][$index]['foreign_amount'] && null !== $group['transactions'][$index]['foreign_amount']) {
                    $group['transactions'][$index]['foreign_amount'] = AmountConverter::positive($group['transactions'][$index]['foreign_amount']);
                }
            }
        }

        return $group;
    }

    public function requiresDefaultAccount(): bool
    {
        return false;
    }

    public function requiresTransactionCurrency(): bool
    {
        return false;
    }
}
