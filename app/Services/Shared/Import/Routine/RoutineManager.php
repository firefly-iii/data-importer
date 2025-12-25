<?php

/*
 * RoutineManager.php
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

namespace App\Services\Shared\Import\Routine;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Import\Status\SubmissionStatus;
use Illuminate\Support\Facades\Log;

/**
 * Class RoutineManager
 */
class RoutineManager
{
    private ApiSubmitter  $apiSubmitter;
    private InfoCollector $infoCollector;

    public function __construct(private ImportJob $importJob)
    {
        $importJob->refreshInstanceIdentifier();
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    private function setConfiguration(): void
    {
        $this->infoCollector = new InfoCollector();
        $this->apiSubmitter  = new ApiSubmitter();
        $this->apiSubmitter->setImportJob($this->importJob);
        Log::debug('Created APISubmitter in RoutineManager');
    }

    /**
     * @throws ImporterErrorException
     */
    public function start(): void
    {
        Log::debug('Start of shared import routine.');
        $this->setConfiguration();

        // FIXME again with the collecting of accounts?
        Log::debug('First collect account information from Firefly III.');
        $accountInfo       = $this->infoCollector->collectAccountTypes();

        Log::debug('Now starting submission by calling API Submitter');
        // submit transactions to API:
        $this->apiSubmitter->setAccountInfo($accountInfo);
        $this->apiSubmitter->processTransactions();
        Log::debug(sprintf('Routine manager: messages: %d, warnings: %d, errors: %d', count($this->importJob->submissionStatus->messages), count($this->importJob->submissionStatus->warnings), count($this->importJob->submissionStatus->errors)));
    }
}
