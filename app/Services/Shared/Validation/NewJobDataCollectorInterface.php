<?php

declare(strict_types=1);
/*
 * NewJobDataCollectorInterface.php
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

namespace App\Services\Shared\Validation;

use App\Models\ImportJob;
use Illuminate\Support\MessageBag;

interface NewJobDataCollectorInterface
{
    public function getFlowName(): string;

    public function getImportJob(): ImportJob;

    public function setImportJob(ImportJob $importJob): void;

    public function validate(): MessageBag;

    public function collectAccounts(): MessageBag;
}
