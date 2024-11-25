<?php

/*
 * ProgressInformation.php
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

namespace App\Services\Shared\Conversion;

use Illuminate\Support\Facades\Log;

/**
 * Trait ProgressInformation
 */
trait ProgressInformation
{
    protected array  $errors     = [];
    protected string $identifier;
    protected array  $messages   = [];
    protected array  $warnings   = [];
    protected array  $rateLimits = [];

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

    final public function setIdentifier(string $identifier): void
    {
        Log::debug(sprintf('Set identifier to "%s"', $identifier));
        $this->identifier = $identifier;
    }

    final protected function addError(int $index, string $error): void
    {
        Log::error(sprintf('[c] Add error to index #%d: %s', $index, $error));
        $this->errors         ??= [];
        $this->errors[$index] ??= [];
        $this->errors[$index][] = $error;

        // write errors to disk
        RoutineStatusManager::addError($this->identifier, $index, $error);
    }

    final protected function addRateLimit(int $index, string $message): void
    {
        Log::error(sprintf('[c] Add rate limit message to index #%d: %s', $index, $message));
        $this->rateLimits         ??= [];
        $this->rateLimits[$index] ??= [];
        $this->rateLimits[$index][] = $message;

        // write errors to disk
        RoutineStatusManager::addRateLimit($this->identifier, $index, $message);
    }

    final protected function addMessage(int $index, string $message): void
    {
        Log::info(sprintf('[c] Add message to index #%d: %s', $index, $message));
        $this->messages         ??= [];
        $this->messages[$index] ??= [];
        $this->messages[$index][] = $message;

        // write message
        RoutineStatusManager::addMessage($this->identifier, $index, $message);
    }

    final protected function addWarning(int $index, string $warning): void
    {
        Log::error(sprintf('[c] Add warning to index #%d: %s', $index, $warning));
        $this->warnings         ??= [];
        $this->warnings[$index] ??= [];
        $this->warnings[$index][] = $warning;

        // write warning
        RoutineStatusManager::addWarning($this->identifier, $index, $warning);
    }
}
