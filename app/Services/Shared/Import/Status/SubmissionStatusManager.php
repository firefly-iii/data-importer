<?php

/*
 * SubmissionStatusManager.php
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

use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Shared\Submission\GeneratesIdentifier;
use Illuminate\Support\Facades\Log;

/**
 * Class SubmissionStatusManager
 * @deprecated
 */
class SubmissionStatusManager
{
    use GeneratesIdentifier;

    protected const string DISK_NAME = 'submission-routines';

    private static function getImportJob(string $identifier): ImportJob
    {
        $repository = new ImportJobRepository();
        $importJob  = $repository->find($identifier);
        $importJob->refreshInstanceIdentifier();
        return $repository->find($identifier);
    }

    private static function storeImportJob(ImportJob $importJob): void
    {
        $repository = new ImportJobRepository();
        $repository->saveToDisk($importJob);
    }

    public static function addError(string $identifier, int $index, string $error): void
    {

        $lineNo = $index + 1;
        Log::debug(sprintf('Add error on index #%d (line no. %d): %s', $index, $lineNo, $error));
        $importJob                                     = self::getImportJob($identifier);
        $importJob->submissionStatus->errors[$index]   ??= [];
        $importJob->submissionStatus->errors[$index][] = $error;
        self::storeImportJob($importJob);
    }

    public static function addMessage(string $identifier, int $index, string $message): void
    {
        $lineNo = $index + 1;
        Log::debug(sprintf('Add message on index #%d (line no. %d): %s', $index, $lineNo, $message));
        $importJob                                       = self::getImportJob($identifier);
        $importJob->submissionStatus->messages[$index]   ??= [];
        $importJob->submissionStatus->messages[$index][] = $message;
        self::storeImportJob($importJob);
    }

    public static function addWarning(string $identifier, int $index, string $warning): void
    {
        $lineNo = $index + 1;
        Log::debug(sprintf('Add warning on index #%d (line no. %d): %s', $index, $lineNo, $warning));
        $importJob                                       = self::getImportJob($identifier);
        $importJob->submissionStatus->warnings[$index]   ??= [];
        $importJob->submissionStatus->warnings[$index][] = $warning;
        self::storeImportJob($importJob);

    }

    public static function updateProgress(string $identifier, int $currentTransaction, int $totalTransactions): void
    {
        Log::debug(sprintf('Update progress for %s: %d/%d transactions', $identifier, $currentTransaction, $totalTransactions));
        $importJob                                       = self::getImportJob($identifier);
        $importJob->submissionStatus->currentTransaction = $currentTransaction;
        $importJob->submissionStatus->totalTransactions  = $totalTransactions;
        $importJob->submissionStatus->progressPercentage = $totalTransactions > 0 ? (int)round(($currentTransaction / $totalTransactions) * 100) : 0;
        self::storeImportJob($importJob);
    }

    public static function setSubmissionStatus(string $status, ?string $identifier = null): SubmissionStatus
    {
        $importJob                           = self::getImportJob($identifier);
        $importJob->submissionStatus->setStatus($status);

        self::storeImportJob($importJob);

        return $importJob->submissionStatus;
    }
}
