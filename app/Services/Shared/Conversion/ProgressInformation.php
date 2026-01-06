<?php

/*
 * ProgressInformation.php
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

namespace App\Services\Shared\Conversion;

use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use Illuminate\Support\Facades\Log;

/**
 * Trait ProgressInformation
 *
 * Replace this with references to the import job, where messages must be stored anyway.
 *
 * @deprecated
 */
trait ProgressInformation
{
    protected array   $errors     = [];
    protected string  $identifier;
    protected array   $messages   = [];
    protected array   $warnings   = [];
    protected array   $rateLimits = [];
    private ImportJob $importJob;

    final public function getErrors(): array
    {
        return $this->errors ?? [];
    }

    public function getRateLimits(): array
    {
        return $this->rateLimits ?? [];
    }

    final public function getMessages(): array
    {
        return $this->messages ?? [];
    }

    final public function getWarnings(): array
    {
        return $this->warnings ?? [];
    }

}
