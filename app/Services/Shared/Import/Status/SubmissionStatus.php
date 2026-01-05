<?php

/*
 * SubmissionStatus.php
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

namespace App\Services\Shared\Import\Status;

use Illuminate\Support\Facades\Log;

class SubmissionStatus
{
    public const string SUBMISSION_DONE    = 'submission_done';
    public const string SUBMISSION_ERRORED = 'submission_errored';
    public const string SUBMISSION_RUNNING = 'submission_running';
    public const string SUBMISSION_WAITING = 'waiting_to_start';
    public array   $errors                 = [];
    public array   $messages               = [];
    private string $status;
    public array   $warnings               = [];
    public int     $currentTransaction     = 0;
    public int     $totalTransactions      = 0;
    public int     $progressPercentage     = 0;

    /**
     * ImportJobStatus constructor.
     */
    public function __construct()
    {
        $this->status = self::SUBMISSION_WAITING;
    }

    public function setStatus(string $status): void
    {
        Log::debug(sprintf('Set submission status to "%s"', $status));
        $this->status = $status;
    }

    public function addError(int $index, string $error): void
    {
        $lineNo                 = $index + 1;
        Log::debug(sprintf('Add error on index #%d (line no. %d): %s', $index, $lineNo, $error));
        $this->errors[$index] ??= [];
        $this->errors[$index][] = $error;
    }

    public function addWarning(int $index, string $warning): void
    {
        $lineNo                   = $index + 1;
        Log::debug(sprintf('Add warning on index #%d (line no. %d): %s', $index, $lineNo, $warning));
        $this->warnings[$index] ??= [];
        $this->warnings[$index][] = $warning;
    }

    public function addMessage(int $index, string $message): void
    {
        $lineNo                   = $index + 1;
        Log::debug(sprintf('Add message on index #%d (line no. %d): %s', $index, $lineNo, $message));
        $this->messages[$index] ??= [];
        $this->messages[$index][] = $message;
    }

    public function updateProgress(int $currentTransaction, int $totalTransactions): void
    {
        Log::debug(sprintf('Update progress: %d/%d transactions', $currentTransaction, $totalTransactions));
        $this->currentTransaction = $currentTransaction;
        $this->totalTransactions  = $totalTransactions;
        $this->progressPercentage = $totalTransactions > 0 ? (int)round(($currentTransaction / $totalTransactions) * 100) : 0;
    }

    /**
     * @return static
     */
    public static function fromArray(array $array): self
    {
        $config                     = new self();
        $config->status             = $array['status'];
        $config->errors             = $array['errors'] ?? [];
        $config->warnings           = $array['warnings'] ?? [];
        $config->messages           = $array['messages'] ?? [];
        $config->currentTransaction = $array['currentTransaction'] ?? 0;
        $config->totalTransactions  = $array['totalTransactions'] ?? 0;
        $config->progressPercentage = $array['progressPercentage'] ?? 0;

        return $config;
    }

    public function toArray(): array
    {
        return [
            'status'             => $this->status,
            'errors'             => $this->errors,
            'warnings'           => $this->warnings,
            'messages'           => $this->messages,
            'currentTransaction' => $this->currentTransaction,
            'totalTransactions'  => $this->totalTransactions,
            'progressPercentage' => $this->progressPercentage,
        ];
    }
}
