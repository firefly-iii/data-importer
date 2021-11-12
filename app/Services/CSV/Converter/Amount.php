<?php
/**
 * Amount.php
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
declare(strict_types=1);

namespace App\Services\CSV\Converter;

use Log;

/**
 * Class Amount.
 */
class Amount implements ConverterInterface
{
    /**
     * @param string $amount
     *
     * @return string
     */
    public static function positive(string $amount): string
    {
        if (-1 === bccomp($amount, '0')) {
            $amount = bcmul($amount, '-1');
        }

        return $amount;
    }

    /**
     * @param string $amount
     *
     * @return string
     */
    public static function negative(string $amount): string
    {
        if (1 === bccomp($amount, '0')) {
            $amount = bcmul($amount, '-1');
        }

        return $amount;
    }

    /**
     * Some people, when confronted with a problem, think "I know, I'll use regular expressions." Now they have two problems.
     * - Jamie Zawinski.
     *
     * @param $value
     *
     * @return string
     */
    public function convert($value): string
    {
        if (null === $value || '' === $value) {
            return '0';
        }

        Log::debug(sprintf('Start with amount "%s"', $value));
        $original = $value;
        $value    = $this->stripAmount((string) $value);
        $decimal  = null;

        if ($this->decimalIsDot($value)) {
            $decimal = '.';
            Log::debug(sprintf('Decimal character in "%s" seems to be a dot.', $value));
        }

        if ($this->decimalIsComma($value)) {
            $decimal = ',';
            Log::debug(sprintf('Decimal character in "%s" seems to be a comma.', $value));
        }

        // decimal character is null? find out if "0.1" or ".1" or "0,1" or ",1"
        if ($this->alternativeDecimalSign($value)) {
            $decimal = $this->getAlternativeDecimalSign($value);
        }

        // decimal character still null? Search from the left for '.',',' or ' '.
        if (null === $decimal) {
            $decimal = $this->findFromLeft($value);
        }

        // if decimal is dot, replace all comma's and spaces with nothing
        if (null !== $decimal) {
            $value = $this->replaceDecimal($decimal, $value);
            Log::debug(sprintf('Converted amount from "%s" to "%s".', $original, $value));
        }

        if (null === $decimal) {
            // replace all:
            $search = ['.', ' ', ','];
            $value  = str_replace($search, '', $value);
            Log::debug(sprintf('No decimal character found. Converted amount from "%s" to "%s".', $original, $value));
        }
        if (0 === strpos($value, '.')) {
            $value = '0' . $value;
        }

        if (is_numeric($value)) {
            Log::debug(sprintf('Final NUMERIC value is: "%s"', $value));

            return $value;
        }
        // @codeCoverageIgnoreStart
        Log::debug(sprintf('Final value is: "%s"', $value));
        $formatted = sprintf('%01.12f', $value);
        Log::debug(sprintf('Is formatted to : "%s"', $formatted));

        return $formatted;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Strip amount from weird characters.
     *
     * @param string $value
     *
     * @return string
     */
    private function stripAmount(string $value): string
    {
        if (0 === strpos($value, '--')) {
            $value = substr($value, 2);
        }
        // have to strip the € because apparantly the Postbank (DE) thinks "1.000,00 €" is a normal way to format a number.
        // 2020-12-01 added "EUR" because another German bank doesn't know what a data format is.
        // This way of stripping exceptions is unsustainable.
        $value = trim((string) str_replace(['€', 'EUR'], '', $value));
        $str   = preg_replace('/[^\-().,0-9 ]/', '', $value);
        $len   = strlen($str);
        if (0 === strpos($str, '(') && ')' === $str[$len - 1]) {
            $str = '-' . substr($str, 1, $len - 2);
        }
        $str = trim($str);

        Log::debug(sprintf('Stripped "%s" away to "%s"', $value, $str));

        return $str;
    }

    /**
     * Helper function to see if the decimal separator is a dot.
     *
     * @param string $value
     *
     * @return bool
     */
    private function decimalIsDot(string $value): bool
    {
        $length          = strlen($value);
        $decimalPosition = $length - 3;

        return ($length > 2 && '.' === $value[$decimalPosition]) || ($length > 2 && strpos($value, '.') > $decimalPosition);
    }

    /**
     * Helper function to see if the decimal separator is a comma.
     *
     * @param string $value
     *
     * @return bool
     */
    private function decimalIsComma(string $value): bool
    {
        $length          = strlen($value);
        $decimalPosition = $length - 3;

        return $length > 2 && ',' === $value[$decimalPosition];
    }

    /**
     * Check if the value has a dot or comma on an alternative place,
     * catching strings like ",1" or ".5".
     *
     * @param string $value
     *
     * @return bool
     */
    private function alternativeDecimalSign(string $value): bool
    {
        $length      = strlen($value);
        $altPosition = $length - 2;

        return $length > 1 && ('.' === $value[$altPosition] || ',' === $value[$altPosition]);
    }

    /**
     * Returns the alternative decimal point used, such as a dot or a comma,
     * from strings like ",1" or "0.5".
     *
     * @param string $value
     *
     * @return string
     */
    private function getAlternativeDecimalSign(string $value): string
    {
        $length      = strlen($value);
        $altPosition = $length - 2;

        return $value[$altPosition];

    }

    /**
     * Search from the left for decimal sign.
     *
     * @param string $value
     *
     * @return string
     */
    private function findFromLeft(string $value): ?string
    {
        $decimal = null;
        Log::debug('Decimal is still NULL, probably number with >2 decimals. Search for a dot.');
        $res = strrpos($value, '.');
        if (false !== $res) {
            // blandly assume this is the one.
            Log::debug(sprintf('Searched from the left for "." in amount "%s", assume this is the decimal sign.', $value));
            $decimal = '.';
        }

        return $decimal;
    }

    /**
     * Replaces other characters like thousand separators with nothing to make the decimal separator the only special
     * character in the string.
     *
     * @param string $decimal
     * @param string $value
     *
     * @return string
     */
    private function replaceDecimal(string $decimal, string $value): string
    {
        $search = [',', ' ']; // default when decimal sign is a dot.
        if (',' === $decimal) {
            $search = ['.', ' '];
        }
        $value = str_replace($search, '', $value);

        /** @noinspection CascadeStringReplacementInspection */
        return str_replace(',', '.', $value);
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
