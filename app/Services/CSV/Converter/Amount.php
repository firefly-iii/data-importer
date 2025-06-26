<?php

/*
 * Amount.php
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

use Illuminate\Support\Facades\Log;

/**
 * Class Amount.
 */
class Amount implements ConverterInterface
{
    public static function negative(string $amount): string
    {
        if (1 === bccomp($amount, '0')) {
            return bcmul($amount, '-1');
        }

        return $amount;
    }

    public static function positive(string $amount): string
    {
        if (-1 === bccomp($amount, '0')) {
            return bcmul($amount, '-1');
        }

        return $amount;
    }

    /**
     * Some people, when confronted with a problem, think "I know, I'll use regular expressions." Now they have two
     * problems.
     * - Jamie Zawinski.
     *
     * @param mixed $value
     */
    public function convert($value): string
    {
        if (null === $value || '' === $value) {
            return '0';
        }

        Log::debug(sprintf('Start with amount "%s"', $value));
        $original    = $value;
        $value       = $this->stripAmount((string) $value);
        $decimal     = null;
        $thousandSep = null;

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
            Log::debug(sprintf('Decimal character in "%s" seems to be "%s".', $value, $decimal));
        }

        // string ends with a dash? strip it
        if (str_ends_with($value, '-')) {
            $trail = substr($value, -1);
            $value = substr($value, 0, -1);
            Log::debug(sprintf('Removed trailing character "%s", results in "%s".', $trail, $value));
        }

        // string ends with dot or comma? strip it.
        if (str_ends_with($value, '.') || str_ends_with($value, ',')) {
            $trail = substr($value, -1);
            $value = substr($value, 0, -1);
            Log::debug(sprintf('Removed trailing decimal character "%s", results in "%s".', $trail, $value));
        }

        // some shitty banks decided that "25.00000" is a normal way to write down numbers.
        // five decimals in the export, WHY
        if (null === $decimal) {
            Log::debug('No decimal point, try to find a dot.');
            $index = strripos($value, '.');
            if (false === $index) {
                Log::debug('No decimal point, try to find a comma.');
                $index = strpos($value, ',');
                if (false === $index) {
                    Log::debug('Found neither, continue.');
                }
            }
            if (false !== $index) {
                $len = strlen($value);
                $pos = $len - $index;
                Log::debug(sprintf('Found decimal point at position %d, length of string is %d, diff is %d.', $index, $len, $pos));
                if (4 === $pos) {
                    if (',' === $value[$index]) {
                        Log::debug('Thousands separator seems to be a comma, decimal separator must be a dot.');
                        $decimal = '.';
                    }
                    if ('.' === $value[$index]) {
                        Log::debug('Thousands separator seems to be a dot, decimal separator must be a comma.');
                        $decimal = ',';
                    }
                }
                if (4 !== $pos) {
                    Log::debug('Decimal point is not at position 4, so probably not a thousands separator.');
                }
            }
        }

        // decimal character still null? Search from the left for '.',',' or ' '.
        if (null === $decimal) {
            // See issue #8404
            $decimal = $this->findFromLeft($value);
            // Log::debug('Disabled "findFromLeft" because it happens more often that "1.000" is thousand than "1.000" is 1 with three zeroes.');
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
        if (str_starts_with($value, '.')) {
            $value = sprintf('0%s',$value);
        }

        if (is_numeric($value)) {
            Log::debug(sprintf('Final NUMERIC value is: "%s"', $value));

            return $value;
        }
        // @codeCoverageIgnoreStart
        Log::debug(sprintf('Final value is: "%s"', $value));
        $formatted   = sprintf('%01.12f', $value);
        Log::debug(sprintf('Is formatted to : "%s"', $formatted));

        return $formatted;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Strip amount from weird characters.
     */
    private function stripAmount(string $value): string
    {
        if (str_starts_with($value, '--')) {
            $value = substr($value, 2);
        }
        // have to strip the € because apparently the Postbank (DE) thinks "1.000,00 €" is a normal way to format a number.
        // 2020-12-01 added "EUR" because another German bank doesn't know what a data format is.
        // This way of stripping exceptions is unsustainable.
        $value = trim((string) str_replace(['€', 'EUR'], '', $value));
        $str   = preg_replace('/[^\-().,0-9 ]/', '', $value);
        $len   = strlen((string) $str);
        if (str_starts_with((string) $str, '(') && ')' === $str[$len - 1]) {
            $str = sprintf('-%s',substr((string) $str, 1, $len - 2));
        }
        $str   = trim((string) $str);

        Log::debug(sprintf('Stripped "%s" to "%s"', $value, $str));

        return $str;
    }

    /**
     * Helper function to see if the decimal separator is a dot.
     */
    private function decimalIsDot(string $value): bool
    {
        $length          = strlen($value);
        $decimalPosition = $length - 3;

        return ($length > 2 && '.' === $value[$decimalPosition]) || ($length > 2 && strpos($value, '.') > $decimalPosition);
    }

    /**
     * Helper function to see if the decimal separator is a comma.
     */
    private function decimalIsComma(string $value): bool
    {
        $length          = strlen($value);
        $decimalPosition = $length - 3;
        $result          = $length > 2 && ',' === $value[$decimalPosition];
        if (true === $result) {
            return true;
        }
        // if false, try to see if this number happens to be formatted like:
        // 0,xxxxxxxxxx
        if (1 === substr_count($value, ',') && str_starts_with($value, '0,')) {
            return true;
        }

        return false;
    }

    /**
     * Check if the value has a dot or comma on an alternative place,
     * catching strings like ",1" or ".5".
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
     */
    private function getAlternativeDecimalSign(string $value): string
    {
        $length      = strlen($value);
        $altPosition = $length - 2;

        return $value[$altPosition];
    }

    /**
     * Search from the left for decimal sign.
     */
    private function findFromLeft(string $value): ?string
    {
        $decimal = null;
        Log::debug('Decimal is still NULL, probably number with >2 decimals. Search for a dot.');
        $res     = strrpos($value, '.');
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
     */
    private function replaceDecimal(string $decimal, string $value): string
    {
        $search = [',', ' ']; // default when decimal sign is a dot.
        if (',' === $decimal) {
            $search = ['.', ' '];
        }
        $value  = str_replace($search, '', $value);

        // @noinspection CascadeStringReplacementInspection
        return str_replace(',', '.', $value);
    }

    /**
     * Add extra configuration parameters.
     */
    public function setConfiguration(string $configuration): void {}
}
