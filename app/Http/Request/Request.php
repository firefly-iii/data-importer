<?php
/**
 * Request.php
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

namespace App\Http\Request;

use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Log;

/**
 * Class Request.
 *
 * @codeCoverageIgnore
 *
 */
class Request extends FormRequest
{
    /**
     * @param string $value
     *
     * @return bool
     */
    public function convertBoolean(?string $value): bool
    {
        if (null === $value) {
            return false;
        }
        if (in_array(trim($value), ['true', 'yes', '1'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Return floating value.
     *
     * @param string $field
     *
     * @return float|null
     */
    public function float(string $field): ?float
    {
        $res = $this->get($field);
        if (null === $res) {
            return null;
        }

        return (float) $res;
    }

    /**
     * Return integer value.
     *
     * @param string $field
     *
     * @return int
     */
    public function integer(string $field): int
    {
        return (int) $this->get($field);
    }

    /**
     * Parse to integer
     *
     * @param string|null $string
     *
     * @return int|null
     */
    public function integerFromValue(?string $string): ?int
    {
        if (null === $string) {
            return null;
        }
        if ('' === $string) {
            return null;
        }

        return (int) $string;
    }

    /**
     * Return integer value, or NULL when it's not set.
     *
     * @param string $field
     *
     * @return int|null
     */
    public function nullableInteger(string $field): ?int
    {
        if (!$this->has($field)) {
            return null;
        }

        $value = (string) $this->get($field);
        if ('' === $value) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Return string value, or NULL if empty.
     *
     * @param string $field
     *
     * @return string|null
     */
    public function nullableString(string $field): ?string
    {
        if (!$this->has($field)) {
            return null;
        }

        return app('steam')->cleanStringAndNewlines((string) ($this->get($field) ?? ''));
    }

    /**
     * Return string value.
     *
     * @param string $field
     *
     * @return string
     */
    public function string(string $field): string
    {
        return app('steam')->cleanStringAndNewlines((string) ($this->get($field) ?? ''));
    }

    /**
     * Parse and clean a string.
     *
     * @param string|null $string
     *
     * @return string|null
     */
    public function stringFromValue(?string $string): ?string
    {
        if (null === $string) {
            return null;
        }
        $result = app('steam')->cleanStringAndNewlines($string);

        return '' === $result ? null : $result;

    }

    /**
     * Return date or NULL.
     *
     * @param string $field
     *
     * @return Carbon|null
     */
    protected function date(string $field): ?Carbon
    {
        $result = null;
        try {
            $result = $this->get($field) ? new Carbon($this->get($field)) : null;
        } catch (Exception $e) {
            Log::debug(sprintf('Exception when parsing date. Not interesting: %s', $e->getMessage()));
        }

        return $result;
    }
}
