<?php

/*
 * RoutineStatusManager.php
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

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use Illuminate\Support\Facades\Log;

/**
 * Class RoutineStatusManager
 */
class RoutineStatusManager
{
    private static function getImportJob(string $identifier): ImportJob
    {
        $repository = new ImportJobRepository();

        return $repository->find($identifier);
    }

    public static function addError(string $identifier, int $index, string $error): void
    {
        $lineNo                                        = $index + 1;
        Log::debug(sprintf('Add error on index #%d (line no. %d): %s', $index, $lineNo, $error));
        $importJob                                     = self::getImportJob($identifier);

        $importJob->conversionStatus->errors[$index] ??= [];
        $importJob->conversionStatus->errors[$index][] = $error;
        self::storeImportJob($importJob);
    }

    public static function addRateLimit(string $identifier, int $index, string $message): void
    {
        $lineNo                                            = $index + 1;
        Log::debug(sprintf('Add rate limit message on index #%d (line no. %d): %s', $index, $lineNo, $message));
        $importJob                                         = self::getImportJob($identifier);
        $importJob->conversionStatus->rateLimits[$index] ??= [];
        $importJob->conversionStatus->rateLimits[$index][] = $message;
        self::storeImportJob($importJob);
    }

    private static function storeImportJob(ImportJob $importJob): void
    {
        Log::debug(sprintf('[%s] Now in storeConversionStatus(%s): %s', config('importer.version'), $identifier, $status->status));
        Log::debug(sprintf('Messages: %d, warnings: %d, errors: %d', count($status->messages), count($status->warnings), count($status->errors)));
        $repository = new ImportJobRepository();
        $repository->saveToDisk($importJob);
    }

    public static function addMessage(string $identifier, int $index, string $message): void
    {
        $lineNo                                          = $index + 1;
        Log::debug(sprintf('Add message on index #%d (line no. %d): %s', $index, $lineNo, $message));
        $importJob                                       = self::getImportJob($identifier);
        $importJob->conversionStatus->messages[$index] ??= [];
        $importJob->conversionStatus->messages[$index][] = $message;
        self::storeImportJob($importJob);
    }

    public static function addWarning(string $identifier, int $index, string $warning): void
    {
        $lineNo                                          = $index + 1;
        Log::debug(sprintf('Add warning on index #%d (line no. %d): %s', $index, $lineNo, $warning));
        $importJob                                       = self::getImportJob($identifier);
        $importJob->conversionStatus->warnings[$index] ??= [];
        $importJob->conversionStatus->warnings[$index][] = $warning;
        self::storeImportJob($importJob);
    }

    /**
     * @throws ImporterErrorException
     */
    public static function setConversionStatus(string $status, string $identifier): ConversionStatus
    {
        Log::debug(sprintf('Now in setConversionStatus(%s, %s)', $status, $identifier));
        $importJob                           = self::getImportJob($identifier);
        $importJob->conversionStatus->status = $status;
        self::storeImportJob($importJob);

        return $importJob->conversionStatus;
    }

    public static function startOrFindConversion(string $identifier): ConversionStatus
    {
        $importJob = self::getImportJob($identifier);

        return $importJob->conversionStatus;
    }
}
