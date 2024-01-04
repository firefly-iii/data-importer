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

namespace App\Services\Shared\Submission;

use App\Services\Shared\Import\Status\SubmissionStatusManager;
use Illuminate\Support\Facades\Log;

/**
 * Trait ProgressInformation
 */
trait ProgressInformation
{
    protected array  $errors = [];
    protected string $identifier;
    protected array  $messages = [];
    protected array  $warnings = [];

    /**
     * @return array
     */
    final public function getErrors(): array
    {
        return $this->errors ?? [];
    }

    /**
     * @return array
     */
    final public function getMessages(): array
    {
        return $this->messages ?? [];
    }

    /**
     * @return array
     */
    final public function getWarnings(): array
    {
        return $this->warnings ?? [];
    }

    /**
     * @param  string  $identifier
     */
    final public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @param  int  $index
     * @param  string  $error
     */
    final protected function addError(int $index, string $error): void
    {
        Log::error(sprintf('Add error to index #%d: %s', $index, $error));
        $this->errors           = $this->errors ?? [];
        $this->errors[$index]   = $this->errors[$index] ?? [];
        $this->errors[$index][] = $error;

        // write errors to disk
        SubmissionStatusManager::addError($this->identifier, $index, $error);
    }

    /**
     * @param  int  $index
     * @param  string  $message
     */
    final protected function addMessage(int $index, string $message): void
    {
        Log::info(sprintf('Add message to index #%d: %s', $index, $message));
        $this->messages           = $this->messages ?? [];
        $this->messages[$index]   = $this->messages[$index] ?? [];
        $this->messages[$index][] = $message;

        // write message
        SubmissionStatusManager::addMessage($this->identifier, $index, $message);
    }

    /**
     * @param  int  $index
     * @param  string  $warning
     */
    final protected function addWarning(int $index, string $warning): void
    {
        Log::error(sprintf('Add warning to index #%d: %s', $index, $warning));
        $this->warnings           = $this->warnings ?? [];
        $this->warnings[$index]   = $this->warnings[$index] ?? [];
        $this->warnings[$index][] = $warning;

        // write warning
        SubmissionStatusManager::addWarning($this->identifier, $index, $warning);
    }
}
