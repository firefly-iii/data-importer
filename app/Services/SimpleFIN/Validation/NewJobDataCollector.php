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
use App\Services\Session\Constants;
use App\Services\SimpleFIN\SimpleFINService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;

class NewJobDataCollector
{
    public bool                 $useDemo    = false;
    public string               $setupToken = '';
    private ImportJobRepository $repository;

    public function __construct()
    {
        $this->repository = new ImportJobRepository();
    }

    public function validate(ImportJob $importJob): MessageBag
    {
        $configuration    = $importJob->getConfiguration();
        $errors           = new MessageBag();
        $accessToken      = $configuration->getAccessToken();
        Log::debug(sprintf('validate("%s") for SimpleFIN', $importJob->identifier));

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
        $importJob->setConfiguration($configuration);
        $this->repository->saveToDisk($importJob);

        return new MessageBag();
    }

    public function collectAccounts(ImportJob $importJob): MessageBag
    {
        $configuration    = $importJob->getConfiguration();
        $errors           = new MessageBag();
        $accessToken      = $configuration->getAccessToken();
        Log::debug(sprintf('collectAccounts("%s")', $importJob->identifier));

        // create service:
        /** @var SimpleFINService $simpleFINService */
        $simpleFINService = app(SimpleFINService::class);
        $simpleFINService->setConfiguration($configuration);
        $simpleFINService->setAccessToken($accessToken);
        $accounts         = [];

        try {
            $accounts = $simpleFINService->fetchAccounts();
        } catch (ImporterErrorException $e) {
            Log::error('SimpleFIN connection failed', ['error' => $e->getMessage()]);
            $errors->add('connection', sprintf('Failed to connect to SimpleFIN: %s', $e->getMessage()));

            return $errors;
        }
        $importJob->setServiceAccounts($accounts);
        $this->repository->saveToDisk($importJob);

        return new MessageBag();
    }

    private function collectSimpleFINAccounts(): void
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        $accountsData                = session()->get(Constants::SIMPLEFIN_ACCOUNTS_DATA, []);
        $accounts                    = [];

        foreach ($accountsData ?? [] as $account) {
            // Ensure the account has required SimpleFIN protocol fields
            if (!array_key_exists('id', $account) || '' === (string)$account['id']) {
                Log::warning('SimpleFIN account data is missing a valid ID, skipping.', ['account_data' => $account]);

                continue;
            }

            if (!array_key_exists('name', $account) || null === $account['name']) {
                Log::warning('SimpleFIN account data is missing name field, adding default.', ['account_id' => $account['id']]);
                $account['name'] = sprintf('Unknown Account (ID: %s)', $account['id']);
            }

            if (!array_key_exists('currency', $account) || null === $account['currency']) {
                Log::warning('SimpleFIN account data is missing currency field, this may cause issues.', ['account_id' => $account['id']]);
            }

            if (!array_key_exists('balance', $account) || null === $account['balance']) {
                Log::warning('SimpleFIN account data is missing balance field, this may cause issues.', ['account_id' => $account['id']]);
            }

            // Preserve raw SimpleFIN protocol data structure
            $accounts[] = $account;
        }
        Log::debug(sprintf('Collected %d SimpleFIN accounts from session.', count($accounts)));
        $this->importServiceAccounts = $accounts;
    }
}
