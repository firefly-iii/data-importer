<?php
/*
 * RoutineStatusManager.php
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

use App\Exceptions\ImporterErrorException;
use App\Services\Session\Constants;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JsonException;
use Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Storage;

/**
 * Class RoutineStatusManager
 */
class RoutineStatusManager
{
    private const DISK_NAME = 'conversion-routines';

    /**
     * @param string $identifier
     * @param int    $index
     * @param string $error
     */
    public static function addError(string $identifier, int $index, string $error): void
    {
        $lineNo = $index + 1;
        Log::debug(sprintf('Add error on index #%d (line no. %d): %s', $index, $lineNo, $error));

        $disk = Storage::disk(self::DISK_NAME);
        try {
            if ($disk->exists($identifier)) {
                try {
                    $status = ConversionStatus::fromArray(json_decode($disk->get($identifier), true, 512, JSON_THROW_ON_ERROR));
                } catch (JsonException $e) {
                    Log::error($e->getMessage());
                    $status = new ConversionStatus;
                }
                $status->errors[$index]   = $status->errors[$index] ?? [];
                $status->errors[$index][] = $error;
                self::storeConversionStatus($identifier, $status);
            }
        } catch (FileNotFoundException $e) {
            Log::error($e->getMessage());
        }
    }

    /**
     * @param string           $identifier
     * @param ConversionStatus $status
     */
    private static function storeConversionStatus(string $identifier, ConversionStatus $status): void
    {
        Log::debug(sprintf('Now in storeConversionStatus(%s): %s', $identifier, $status->status));
        $disk = Storage::disk(self::DISK_NAME);
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

        $disk = Storage::disk(self::DISK_NAME);
        try {
            if ($disk->exists($identifier)) {
                try {
                    $status = ConversionStatus::fromArray(json_decode($disk->get($identifier), true, 512, JSON_THROW_ON_ERROR));
                } catch (JsonException $e) {
                    Log::error($e->getMessage());
                    $status = new ConversionStatus;
                }
                $status->warnings[$index]   = $status->warnings[$index] ?? [];
                $status->warnings[$index][] = $warning;
                self::storeConversionStatus($identifier, $status);
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

        $disk = Storage::disk(self::DISK_NAME);
        try {
            if ($disk->exists($identifier)) {
                try {
                    $status = ConversionStatus::fromArray(json_decode($disk->get($identifier), true, 512, JSON_THROW_ON_ERROR));
                } catch (JsonException $e) {
                    Log::error($e->getMessage());
                    $status = new ConversionStatus;
                }
                $status->messages[$index]   = $status->messages[$index] ?? [];
                $status->messages[$index][] = $message;
                self::storeConversionStatus($identifier, $status);
            }
        } catch (FileNotFoundException $e) {
            Log::error($e->getMessage());
        }
    }


    /**
     * @param string $status
     *
     * @return ConversionStatus
     * @throws ImporterErrorException
     */
    public static function setConversionStatus(string $status): ConversionStatus
    {
        try {
            $identifier = session()->get(Constants::CONVERSION_JOB_IDENTIFIER);
        } catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
            throw new ImporterErrorException('No identifier found');
        }
        Log::debug(sprintf('Now in setConversionStatus(%s)', $status));
        Log::debug(sprintf('Found "%s" in the session', $identifier));

        $jobStatus         = self::startOrFindConversion($identifier);
        $jobStatus->status = $status;

        self::storeConversionStatus($identifier, $jobStatus);

        return $jobStatus;
    }

    /**
     * @param string $identifier
     *
     * @return ConversionStatus
     */
    public static function startOrFindConversion(string $identifier): ConversionStatus
    {
        Log::debug(sprintf('Now in startOrFindConversion(%s)', $identifier));
        $disk = Storage::disk(self::DISK_NAME);
        Log::debug(sprintf('Try to see if file exists for conversion "%s".', $identifier));
        if ($disk->exists($identifier)) {
            Log::debug(sprintf('Status file exists for conversion "%s".', $identifier));
            try {
                $array  = json_decode($disk->get($identifier), true, 512, JSON_THROW_ON_ERROR);
                $status = ConversionStatus::fromArray($array);
            } catch (FileNotFoundException | JsonException $e) {
                Log::error($e->getMessage());
                $status = new ConversionStatus;
            }

            return $status;

        }
        Log::debug('File does not exist or error, create a new one.');
        $status = new ConversionStatus;
        try {
            $disk->put($identifier, json_encode($status->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } catch (JsonException $e) {
            Log::error($e->getMessage());
        }

        Log::debug('Return status.', $status->toArray());

        return $status;
    }
}
