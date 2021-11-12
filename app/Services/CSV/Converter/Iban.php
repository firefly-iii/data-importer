<?php
declare(strict_types=1);
/**
 * Iban.php
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

namespace App\Services\CSV\Converter;

use Log;
use ValueError;

/**
 * Class Iban
 */
class Iban implements ConverterInterface
{

    /**
     * Convert a value.
     *
     * @param $value
     *
     * @return mixed
     *
     */
    public function convert($value)
    {
        if ($this->isValidIban($value)) {
            // strip spaces from IBAN and make upper case.
            $result = str_replace("\x20", '', strtoupper(app('steam')->cleanStringAndNewlines($value)));
            Log::debug(sprintf('Converted "%s" to "%s"', $value, $result));

            return $result;
        }
        Log::info(sprintf('"%s" is not a valid IBAN.', $value));

        return '';
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private function isValidIban(string $value): bool
    {
        Log::debug(sprintf('isValidIBAN("%s")', $value));
        $value = strtoupper(trim(app('steam')->cleanStringAndNewlines($value)));
        $value = str_replace("\x20", '', $value);
        Log::debug(sprintf('Trim: isValidIBAN("%s")', $value));
        $search  = [' ', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        $replace = ['', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31',
                    '32', '33', '34', '35',];
        // take
        $first = substr($value, 0, 4);
        $last  = substr($value, 4);
        $iban  = $last . $first;
        $iban  = str_replace($search, $replace, $iban);
        try {
            $checksum = bcmod($iban, '97');
        } catch (ValueError $e) {
            Log::error(sprintf('Bad IBAN: %s', $e->getMessage()));
            $checksum = 2;
        }

        return 1 === (int) $checksum;
    }

    /**
     * Add extra configuration parameters.
     *
     * @param string $configuration
     */
    public function setConfiguration(string $configuration): void
    {

    }
}
