<?php

declare(strict_types=1);
/*
 * ConversionRoutineFactory.php
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

namespace App\Services\Shared\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Services\Camt\Conversion\RoutineManager as CamtRoutineManager;
use App\Services\Sophtron\Conversion\RoutineManager as SophtronRoutineManager;
use App\Services\CSV\Conversion\RoutineManager as CSVRoutineManager;
use App\Services\LunchFlow\Conversion\RoutineManager as LunchFlowRoutineManager;
use App\Services\Nordigen\Conversion\RoutineManager as NordigenRoutineManager;
use App\Services\Shared\File\FileContentSherlock;
use App\Services\SimpleFIN\Conversion\RoutineManager as SimpleFINRoutineManager;
use Illuminate\Support\Facades\Log;

class ConversionRoutineFactory
{
    private ImportJob $importJob;

    public function __construct(ImportJob $importJob)
    {
        $this->importJob = $importJob;
    }

    public function createManager(): RoutineManagerInterface
    {
        Log::debug('Create a routine.');
        $flow          = $this->importJob->getFlow();
        $configuration = $this->importJob->getConfiguration();
        $manager       = null;

        if ('file' === $flow) {
            $contentType = $configuration->getContentType();
            if ('unknown' === $contentType) {
                Log::debug('Content type is "unknown" in startConversion(), detect it.');
                $detector    = new FileContentSherlock();
                $contentType = $detector->detectContentType($this->importJob->getImportableFileString($configuration->isConversion()));
            }
            if ('unknown' === $contentType || 'csv' === $contentType) {
                Log::debug(sprintf('Content type is "%s" in startConversion(), use the CSV routine.', $contentType));

                return new CSVRoutineManager($this->importJob);
            }
            if ('camt' === $contentType) {
                Log::debug('Content type is "camt" in startConversion(), use the CAMT routine.');

                return new CamtRoutineManager($this->importJob);
            }
        }
        if ('sophtron' === $flow) {
            return new SophtronRoutineManager($this->importJob);
        }
        if ('nordigen' === $flow) {
            return new NordigenRoutineManager($this->importJob);
        }
        if ('simplefin' === $flow) {
            return new SimpleFINRoutineManager($this->importJob);
        }
        if ('lunchflow' === $flow) {
            return new LunchFlowRoutineManager($this->importJob);
        }

        throw new ImporterErrorException(sprintf('ConversionRoutineFactory cannot create a routine for import flow "%s"', $flow));
    }
}
