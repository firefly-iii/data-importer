<?php

/*
 * AutoImportResult.php
 * Copyright (c) 2026 james@firefly-iii.org
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

namespace App\Console;

use App\Enums\ExitCode;

final readonly class AutoImportResult
{
    /**
     * @param array<string, int> $result
     */
    public function __construct(
        private array $result
    ) {}

    /**
     * @return array<string, int>
     */
    public function getFailures(): array
    {
        $benign = [ExitCode::SUCCESS->value, ExitCode::NOTHING_WAS_IMPORTED->value];

        return array_filter($this->result, static fn(int $code): bool => !in_array($code, $benign, true));
    }

    public function getExitCode(): ExitCode
    {
        $failures = $this->getFailures();
        if (0 === count($failures)) {
            return in_array(ExitCode::SUCCESS->value, $this->result, true) ? ExitCode::SUCCESS : ExitCode::NOTHING_WAS_IMPORTED;
        }

        $distinct = array_unique($failures);

        return 1 === count($distinct) ? ExitCode::from((int) reset($distinct)) : ExitCode::GENERAL_ERROR;
    }
}
