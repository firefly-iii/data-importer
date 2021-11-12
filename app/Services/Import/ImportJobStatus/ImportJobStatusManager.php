<?php
declare(strict_types=1);
/**
 * ImportJobStatusManager.php
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

namespace App\Services\Import\ImportJobStatus;

use App\Services\Session\Constants;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JsonException;
use Log;
use Storage;

/**
 * Class ImportJobStatusManager
 */
class ImportJobStatusManager
{
    /**
     * @param string $identifier
     * @param int    $index
     * @param string $error
     */
    public static function addError(string $identifier, int $index, string $error): void
    {
        $lineNo = $index + 1;
        Log::debug(sprintf('Add error on index #%d (line no. %d): %s', $index, $lineNo, $error));

        $disk = Storage::disk('jobs');
        try {
            if ($disk->exists($identifier)) {
                try {
                    $status = ImportJobStatus::fromArray(json_decode($disk->get($identifier), true, 512, JSON_THROW_ON_ERROR));
                } catch (JsonException $e) {
                    $status = new ImportJobStatus;
                }
                $status->errors[$index]   = $status->errors[$index] ?? [];
                $status->errors[$index][] = $error;
                self::storeJobStatus($identifier, $status);
            }
        } catch (FileNotFoundException $e) {
            Log::error($e->getMessage());
        }
    }

    /**
     * @param string          $identifier
     * @param ImportJobStatus $status
     */
    private static function storeJobStatus(string $identifier, ImportJobStatus $status): void
    {
        Log::debug(sprintf('Now in storeJobStatus(%s): %s', $identifier, $status->status));
        $disk = Storage::disk('jobs');
        try {
            $disk->put($identifier, json_encode($status->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } catch (JsonException $e) {
            // do nothing
            Log::error($e->getMessage());
        }
    }

    /**
     * @param string $identifier
     * @param int    $index
     * @param string $warning
     *
     */
    public static function addWarning(string $identifier, int $index, string $warning): void
    {
        $lineNo = $index + 1;
        Log::debug(sprintf('Add warning on index #%d (line no. %d): %s', $index, $lineNo, $warning));

        $disk = Storage::disk('jobs');
        try {
            if ($disk->exists($identifier)) {
                try {
                    $status = ImportJobStatus::fromArray(json_decode($disk->get($identifier), true, 512, JSON_THROW_ON_ERROR));
                } catch (JsonException $e) {
                    $status = new ImportJobStatus;
                }
                $status->warnings[$index]   = $status->warnings[$index] ?? [];
                $status->warnings[$index][] = $warning;
                self::storeJobStatus($identifier, $status);
            }
        } catch (FileNotFoundException $e) {
            Log::error($e->getMessage());
        }
    }

    /**
     * @param string $identifier
     * @param int    $index
     * @param string $message
     *
     */
    public static function addMessage(string $identifier, int $index, string $message): void
    {
        $lineNo = $index + 1;
        Log::debug(sprintf('Add message on index #%d (line no. %d): %s', $index, $lineNo, $message));

        $disk = Storage::disk('jobs');
        try {
            if ($disk->exists($identifier)) {
                try {
                    $status = ImportJobStatus::fromArray(json_decode($disk->get($identifier), true, 512, JSON_THROW_ON_ERROR));
                } catch (JsonException $e) {
                    $status = new ImportJobStatus;
                }
                $status->messages[$index]   = $status->messages[$index] ?? [];
                $status->messages[$index][] = $message;
                self::storeJobStatus($identifier, $status);
            }
        } catch (FileNotFoundException $e) {
            Log::error($e->getMessage());
        }
    }


    /**
     * @param string $status
     *
     * @return ImportJobStatus
     * @throws JsonException
     * @throws JsonException
     */
    public static function setJobStatus(string $status): ImportJobStatus
    {
        $identifier = session()->get(Constants::JOB_IDENTIFIER);
        Log::debug(sprintf('Now in setJobStatus(%s)', $status));
        Log::debug(sprintf('Found "%s" in the session', $identifier));

        $jobStatus         = self::startOrFindJob($identifier);
        $jobStatus->status = $status;

        self::storeJobStatus($identifier, $jobStatus);

        return $jobStatus;
    }

    /**
     * @param string $identifier
     *
     * @return ImportJobStatus
     */
    public static function startOrFindJob(string $identifier): ImportJobStatus
    {
        Log::debug(sprintf('Now in startOrFindJob(%s)', $identifier));
        $disk = Storage::disk('jobs');
        try {
            Log::debug(sprintf('Try to see if file exists for job %s.', $identifier));
            if ($disk->exists($identifier)) {
                Log::debug(sprintf('Status file exists for job %s.', $identifier));
                try {
                    $array  = json_decode($disk->get($identifier), true, 512, JSON_THROW_ON_ERROR);
                    $status = ImportJobStatus::fromArray($array);
                } catch (FileNotFoundException | JsonException $e) {
                    Log::error($e->getMessage());
                    $status = new ImportJobStatus;
                }

                return $status;

            }
        } catch (FileNotFoundException $e) {
            Log::error('Could not find file, write a new one.');
            Log::error($e->getMessage());
        }
        Log::debug('File does not exist or error, create a new one.');
        $status = new ImportJobStatus;
        $disk->put($identifier, json_encode($status->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        Log::debug('Return status.', $status->toArray());

        return $status;
    }
}
