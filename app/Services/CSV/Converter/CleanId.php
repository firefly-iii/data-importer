<?php

/*
 * CleanId.php
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
 * Class CleanId
 */
class CleanId implements ConverterInterface
{
    /**
     * Convert a value.
     */
    public function convert(mixed $value): ?int
    {
        Log::debug(sprintf('Now applying CleanId converter on "%s"', $value));
        $value = (int) $value;

        return 0 === $value ? null : $value;
    }

    /**
     * Add extra configuration parameters.
     */
    public function setConfiguration(string $configuration): void {}
}
