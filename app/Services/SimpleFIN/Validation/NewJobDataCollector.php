<?php

declare(strict_types=1);
/*
 * NewJobValidator.php
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

namespace App\Services\SimpleFIN\Validation;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Shared\Validation\NewJobDataCollectorInterface;
use App\Services\SimpleFIN\SimpleFINService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;

class NewJobDataCollector implements NewJobDataCollectorInterface
{
    public bool                 $useDemo    = false;
    public string               $setupToken = '';
    private ImportJob           $importJob;
    private ImportJobRepository $repository;

    public function __construct()
    {
        $this->repository = new ImportJobRepository();
    }

    public function validate(): MessageBag
    {
        $this->importJob->refreshInstanceIdentifier(); // to make sure the information stays fresh.
        $configuration = $this->importJob->getConfiguration();
        $errors        = new MessageBag();
        $accessToken   = $configuration->getAccessToken();
        Log::debug(sprintf('validate("%s") for SimpleFIN', $this->importJob->identifier));

        if ($this->useDemo) {
            Log::debug('Overrule info with demo info.');
            $this->setupToken = (string)config('simplefin.demo_token');
        }

        if ('' === $this->setupToken && '' === $accessToken) {
            $errors->add('simplefin_token', 'SimpleFIN token is required.');

            return $errors;
        }
        if ('' !== $accessToken) {
            return $errors;
        }

        // create service:
        /** @var SimpleFINService $simpleFINService */
        $simpleFINService = app(SimpleFINService::class);
        $simpleFINService->setSetupToken($this->setupToken);
        $simpleFINService->setConfiguration($configuration);

        try {
            // try to get an access token, if not already present in configuration.
            Log::debug('Will collect access token from SimpleFIN using setup token.');
            $simpleFINService->exchangeSetupTokenForAccessToken();
            $accessToken = $simpleFINService->getAccessToken();
        } catch (ImporterErrorException $e) {
            Log::error('SimpleFIN connection failed, could not exchange token.', ['error' => $e->getMessage()]);
            $errors->add('simplefin_token', sprintf('Failed to connect to SimpleFIN: %s', $e->getMessage()));

            return $errors;
        }
        // update config, update import job and DONE.
        $configuration->setAccessToken($accessToken);
        $this->importJob->setConfiguration($configuration);
        $this->repository->saveToDisk($this->importJob);

        return new MessageBag();
    }

    public function collectAccounts(): MessageBag
    {
        $configuration = $this->importJob->getConfiguration();
        $errors        = new MessageBag();
        $accessToken   = $configuration->getAccessToken();
        Log::debug(sprintf('collectAccounts("%s")', $this->importJob->identifier));

        // create service:
        /** @var SimpleFINService $simpleFINService */
        $simpleFINService = app(SimpleFINService::class);
        $simpleFINService->setConfiguration($configuration);
        $simpleFINService->setAccessToken($accessToken);
        $accounts = [];

        try {
            $accounts = $simpleFINService->fetchAccounts();
        } catch (ImporterErrorException $e) {
            Log::error('SimpleFIN connection failed', ['error' => $e->getMessage()]);
            $errors->add('connection', sprintf('Failed to connect to SimpleFIN: %s', $e->getMessage()));

            return $errors;
        }
        $this->importJob->setServiceAccounts($accounts);
        $this->repository->saveToDisk($this->importJob);

        return new MessageBag();
    }

    public function getFlowName(): string
    {
        return 'simplefin';
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function setImportJob(ImportJob $importJob): void
    {
        $this->importJob = $importJob;
    }
}
