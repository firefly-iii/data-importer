<?php
declare(strict_types=1);
/**
 * ProgressInformation.php
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

namespace App\Services\Import\Support;

use App\Services\Import\ImportJobStatus\ImportJobStatusManager;

/**
 * Trait ProgressInformation
 */
trait ProgressInformation
{
    /** @var array */
    protected $errors;
    /** @var array */
    protected $messages;
    /** @var array */
    protected $warnings;
    /** @var string */
    protected $identifier;

    /**
     * @param string $identifier
     */
    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors ?? [];
    }

    /**
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages ?? [];
    }

    /**
     * @return array
     */
    public function getWarnings(): array
    {
        return $this->warnings ?? [];
    }

    /**
     * @param int    $index
     * @param string $error
     */
    protected function addError(int $index, string $error): void
    {
        $this->errors           = $this->errors ?? [];
        $this->errors[$index]   = $this->errors[$index] ?? [];
        $this->errors[$index][] = $error;

        // write errors
        ImportJobStatusManager::addError($this->identifier, $index, $error);
    }

    /**
     * @param int    $index
     * @param string $message
     */
    protected function addMessage(int $index, string $message): void
    {
        $this->messages           = $this->messages ?? [];
        $this->messages[$index]   = $this->messages[$index] ?? [];
        $this->messages[$index][] = $message;

        // write message
        ImportJobStatusManager::addMessage($this->identifier, $index, $message);
    }

    /**
     * @param int    $index
     * @param string $warning
     */
    protected function addWarning(int $index, string $warning): void
    {
        $this->warnings           = $this->warnings ?? [];
        $this->warnings[$index]   = $this->warnings[$index] ?? [];
        $this->warnings[$index][] = $warning;

        // write warning
        ImportJobStatusManager::addWarning($this->identifier, $index, $warning);
    }


}
