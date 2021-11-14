<?php
/*
 * RoutineManagerInterface.php
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

namespace App\Services\Shared\Conversion;

use App\Services\CSV\Configuration\Configuration;

/**
 * Interface RoutineManagerInterface
 */
interface RoutineManagerInterface
{
    /**
     * @param Configuration $configuration
     */
    public function setConfiguration(Configuration $configuration): void;

    /**
     *
     */
    public function start(): array;

    /**
     * @return string
     */
    public function getIdentifier(): string;

}
