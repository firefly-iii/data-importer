<?php

/*
 * CleanUrl.php
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

namespace App\Services\CSV\Converter;

/**
 * Class CleanUrl
 */
class CleanUrl implements ConverterInterface
{
    /**
     * Convert a value.
     */
    public function convert(mixed $value): ?string
    {
        $value = app('steam')->cleanStringAndNewlines($value);

        // also remove newlines:
        $value = trim(str_replace("\n", '', $value));
        if (false !== filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return null;
    }

    /**
     * Add extra configuration parameters.
     */
    public function setConfiguration(string $configuration): void {}
}
