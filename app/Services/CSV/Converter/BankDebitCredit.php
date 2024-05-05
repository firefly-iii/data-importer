<?php
/*
 * BankDebitCredit.php
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

namespace App\Services\CSV\Converter;

/**
 * Class BankDebitCredit
 */
class BankDebitCredit implements ConverterInterface
{
    /**
     * Convert a value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function convert($value): int
    {
        app('log')->debug('Going to convert ', ['value' => $value]);

        // Note: the array values should be all lowercase
        $negative = [
            'd', // Old style Rabobank (NL). Short for "Debit"
            'a', // New style Rabobank (NL). Short for "Af"
            'dr', // https://old.reddit.com/r/FireflyIII/comments/bn2edf/generic_debitcredit_indicator/
            'af', // ING (NL).
            'debet', // Triodos (NL)
            'debit', // ING (EN), thx Quibus!
            's', // Volksbank (DE), Short for "Soll"
            'dbit', // https://subsembly.com/index.html (Banking4 App)
            'charge', // not sure which bank but it's insane.
        ];

        // Lowercase the value and trim it for comparison.
        if (in_array(strtolower(trim($value)), $negative, true)) {
            return -1;
        }

        return 1;
    }

    /**
     * Add extra configuration parameters.
     */
    public function setConfiguration(string $configuration): void {}
}
