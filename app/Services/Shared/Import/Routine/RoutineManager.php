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
use Illuminate\Support\Facades\Log;

/**
 * Class RoutineManager
 */
class RoutineManager
{
    private array         $allErrors;
    private array         $allMessages;
    private array         $allWarnings;
    private ApiSubmitter  $apiSubmitter;
    private InfoCollector $infoCollector;
    private array         $transactions;

    public function __construct(private ImportJob $importJob)
    {
        $this->transactions = [];
        $this->allMessages  = [];
        $this->allWarnings  = [];
        $this->allErrors    = [];
    }

    public function getAllErrors(): array
    {
        return $this->allErrors;
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function getAllMessages(): array
    {
        return $this->allMessages;
    }

    public function getAllWarnings(): array
    {
        return $this->allWarnings;
    }

    private function setConfiguration(Configuration $configuration): void
    {
        $this->infoCollector = new InfoCollector();
        $this->apiSubmitter  = new ApiSubmitter();
        $this->apiSubmitter->setImportJob($this->importJob);
        Log::debug('Created APISubmitter in RoutineManager');
    }

    public function setTransactions(array $transactions): void
    {
        $this->transactions = $transactions;
        Log::debug(sprintf('Now have %d transaction(s) in RoutineManager', count($transactions)));
    }

    /**
     * @throws ImporterErrorException
     */
    public function start(): void
    {
        Log::debug('Start of shared import routine.');
        $this->setConfiguration($this->importJob->getConfiguration());

        Log::debug('First collect account information from Firefly III.');
        $accountInfo       = $this->infoCollector->collectAccountTypes();

        Log::debug('Now starting submission by calling API Submitter');
        // submit transactions to API:
        $this->apiSubmitter->setAccountInfo($accountInfo);
        $this->apiSubmitter->processTransactions();
        $this->allMessages = $this->apiSubmitter->getMessages();
        $this->allWarnings = $this->apiSubmitter->getWarnings();
        $this->allErrors   = $this->apiSubmitter->getErrors();
        Log::debug(sprintf('Routine manager: messages: %d, warnings: %d, errors: %d', count($this->allMessages), count($this->allWarnings), count($this->allErrors)));
    }
}
