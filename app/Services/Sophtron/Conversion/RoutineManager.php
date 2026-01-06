<?php

declare(strict_types=1);
/*
 * RoutineManager.php
 * Copyright (c) 2026 james@firefly-iii.org
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

namespace App\Services\Sophtron\Conversion;

use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Shared\Conversion\CombinedProgressInformation;
use App\Services\Shared\Conversion\ProgressInformation;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use Illuminate\Support\Facades\Log;

class RoutineManager implements RoutineManagerInterface
{
    use CombinedProgressInformation;
    private ImportJob             $importJob;
    private ImportJobRepository   $repository;
    private TransactionDownloader $downloader;
    private TransactionConverter  $converter;

    public function __construct(ImportJob $importJob)
    {
        Log::debug('Constructed CAMT RoutineManager');
        $this->importJob  = $importJob;
        $this->repository = new ImportJobRepository();
        $this->importJob->refreshInstanceIdentifier();
    }

    public function getServiceAccounts(): array
    {
        return [];
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function start(): array
    {
        // downloads raw transactions, save as much info as possible.
        $this->downloader = new TransactionDownloader($this->importJob);
        $downloaded       = $this->downloader->download();

        // get import job back from downloader.
        $this->importJob  = $this->downloader->getImportJob();
        $this->repository->saveToDisk($this->importJob);

        // convert to Firefly III compatible arrays, fixes mapping if necessary.
        $this->converter  = new TransactionConverter($this->importJob);
        $transactions     = $this->converter->convert($downloaded);
        $this->importJob  = $this->converter->getImportJob();
        $this->repository->saveToDisk($this->importJob);

        return $transactions;
    }
}
