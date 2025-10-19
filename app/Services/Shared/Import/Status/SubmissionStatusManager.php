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

use App\Services\Session\Constants;
use App\Services\Shared\Submission\GeneratesIdentifier;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use JsonException;
use Storage;

/**
 * Class SubmissionStatusManager
 */
class SubmissionStatusManager
{
    use GeneratesIdentifier;

    protected const string DISK_NAME = 'submission-routines';

    public static function addError(string $identifier, int $index, string $error): void
    {
        $lineNo = $index + 1;
        Log::debug(sprintf('Add error on index #%d (line no. %d): %s', $index, $lineNo, $error));
        $disk   = Storage::disk(self::DISK_NAME);

        try {
            if ($disk->exists($identifier)) {
                try {
                    $status = SubmissionStatus::fromArray(json_decode((string) $disk->get($identifier), true, 512, JSON_THROW_ON_ERROR));
                } catch (JsonException) {
                    $status = new SubmissionStatus();
                }
                $status->errors[$index] ??= [];
                $status->errors[$index][] = $error;
                self::storeSubmissionStatus($identifier, $status);
            }
            if (!$disk->exists($identifier)) {
                Log::error(sprintf('Could not find file for job %s.', $identifier));
            }
        } catch (FileNotFoundException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
        }
    }

    private static function storeSubmissionStatus(string $identifier, SubmissionStatus $status): void
    {
        Log::debug(sprintf('Now in %s(%s): %s', __METHOD__, $identifier, $status->status));
        Log::debug(sprintf('Messages: %d, warnings: %d, errors: %d', count($status->messages), count($status->warnings), count($status->errors)));
        $disk = Storage::disk(self::DISK_NAME);

        try {
            $disk->put($identifier, json_encode($status->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } catch (JsonException $e) {
            // do nothing
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
        }
    }

    public static function addMessage(string $identifier, int $index, string $message): void
    {
        $lineNo = $index + 1;
        Log::debug(sprintf('Add message on index #%d (line no. %d): %s', $index, $lineNo, $message));

        $disk   = Storage::disk(self::DISK_NAME);

        try {
            if ($disk->exists($identifier)) {
                try {
                    $status = SubmissionStatus::fromArray(json_decode((string) $disk->get($identifier), true, 512, JSON_THROW_ON_ERROR));
                } catch (JsonException) {
                    $status = new SubmissionStatus();
                }
                $status->messages[$index] ??= [];
                $status->messages[$index][] = $message;
                self::storeSubmissionStatus($identifier, $status);
            }
            if (!$disk->exists($identifier)) {
                Log::error(sprintf('Could not find file for job %s.', $identifier));
            }
        } catch (FileNotFoundException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
        }
    }

    public static function addWarning(string $identifier, int $index, string $warning): void
    {
        $lineNo = $index + 1;
        Log::debug(sprintf('Add warning on index #%d (line no. %d): %s', $index, $lineNo, $warning));

        $disk   = Storage::disk(self::DISK_NAME);

        try {
            if ($disk->exists($identifier)) {
                try {
                    $status = SubmissionStatus::fromArray(json_decode((string) $disk->get($identifier), true, 512, JSON_THROW_ON_ERROR));
                } catch (JsonException) {
                    $status = new SubmissionStatus();
                }
                $status->warnings[$index] ??= [];
                $status->warnings[$index][] = $warning;
                self::storeSubmissionStatus($identifier, $status);
            }
            if (!$disk->exists($identifier)) {
                Log::error(sprintf('Could not find file for job %s.', $identifier));
            }
        } catch (FileNotFoundException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
        }
    }

    public static function updateProgress(string $identifier, int $currentTransaction, int $totalTransactions): void
    {
        Log::debug(sprintf('Update progress for %s: %d/%d transactions', $identifier, $currentTransaction, $totalTransactions));

        $disk = Storage::disk(self::DISK_NAME);

        try {
            if ($disk->exists($identifier)) {
                try {
                    $status = SubmissionStatus::fromArray(json_decode((string) $disk->get($identifier), true, 512, JSON_THROW_ON_ERROR));
                } catch (JsonException) {
                    $status = new SubmissionStatus();
                }

                $status->currentTransaction = $currentTransaction;
                $status->totalTransactions  = $totalTransactions;
                $status->progressPercentage = $totalTransactions > 0 ? (int) round(($currentTransaction / $totalTransactions) * 100) : 0;

                self::storeSubmissionStatus($identifier, $status);
            }
            if (!$disk->exists($identifier)) {
                Log::error(sprintf('Could not find file for job %s.', $identifier));
            }
        } catch (FileNotFoundException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
        }
    }

    public static function setSubmissionStatus(string $status, ?string $identifier = null): SubmissionStatus
    {
        if (null === $identifier) {
            try {
                $identifier = session()->get(Constants::IMPORT_JOB_IDENTIFIER);
            } catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
                Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
                $identifier = 'error-setSubmissionStatus';
            }
        }
        Log::debug(sprintf('Now in setSubmissionStatus(%s)', $status));
        Log::debug(sprintf('Found "%s" in the session', $identifier));

        $jobStatus         = self::startOrFindSubmission($identifier);
        $jobStatus->status = $status;

        self::storeSubmissionStatus($identifier, $jobStatus);

        return $jobStatus;
    }

    public static function startOrFindSubmission(string $identifier): SubmissionStatus
    {
        Log::debug(sprintf('Now in startOrFindJob(%s)', $identifier));
        $disk   = Storage::disk(self::DISK_NAME);

        try {
            Log::debug(sprintf('Try to see if file exists for job %s.', $identifier));
            if ($disk->exists($identifier)) {
                Log::debug(sprintf('Status file exists for job %s.', $identifier));

                try {
                    $array  = json_decode((string) $disk->get($identifier), true, 512, JSON_THROW_ON_ERROR);
                    $status = SubmissionStatus::fromArray($array);
                } catch (FileNotFoundException|JsonException $e) {
                    Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
                    $status = new SubmissionStatus();
                }
                Log::debug(sprintf('Status: %s', $status->status));
                Log::debug(sprintf('Messages: %d, warnings: %d, errors: %d', count($status->messages), count($status->warnings), count($status->errors)));

                return $status;
            }
        } catch (FileNotFoundException $e) {
            Log::error('Could not find file, write a new one.');
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
        }
        Log::debug('File does not exist or error, create a new one.');
        $status = new SubmissionStatus();

        try {
            $json = json_encode($status->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
            $json = '{}';
        }
        $disk->put($identifier, $json);

        Log::debug('Return status.', $status->toArray());

        return $status;
    }
}
