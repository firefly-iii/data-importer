<?php

/*
 * NewJobDataCollector.php
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

namespace App\Services\EnableBanking\Validation;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\EnableBanking\Model\Account;
use App\Services\EnableBanking\Request\GetAccountsRequest;
use App\Services\EnableBanking\Response\AccountsResponse;
use App\Services\Shared\Validation\NewJobDataCollectorInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;

/**
 * Class NewJobDataCollector
 */
class NewJobDataCollector implements NewJobDataCollectorInterface
{
    private ImportJob $importJob;
    private ImportJobRepository $repository;

    public function __construct()
    {
        $this->repository = new ImportJobRepository();
    }

    public function collectAccounts(): MessageBag
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        $this->importJob->refreshInstanceIdentifier();
        $messageBag = new MessageBag();
        $configuration = $this->importJob->getConfiguration();
        $sessions = $configuration->getEnableBankingSessions();

        if (0 === count($sessions)) {
            Log::debug('No Enable Banking sessions for import.');
            $messageBag->add('missing_sessions', 'true');

            return $messageBag;
        }

        Log::debug(sprintf('Have %d session(s) for import.', count($sessions)));

        // Check if accounts were already saved from the session response (callback)
        $existingAccounts = $this->importJob->getServiceAccounts();
        if (count($existingAccounts) > 0) {
            Log::debug(sprintf('Already have %d accounts from session response, skipping API fetch.', count($existingAccounts)));

            return $messageBag;
        }

        // No accounts saved yet, try to fetch from API (fallback for older sessions)
        $return = [];
        $cache = [];

        foreach ($sessions as $sessionId) {
            $cacheKey = sprintf('eb_session_%s', $sessionId);
            $inCache = Cache::has($cacheKey) && config('importer.use_cache');

            if ($inCache) {
                Log::debug('Have accounts in cache.');
                $result = Cache::get($cacheKey);
                foreach ($result as $arr) {
                    $return[] = Account::fromLocalArray($arr);
                }
                Log::debug('Grab accounts from cache', $result);
            }

            if (!$inCache) {
                Log::debug('Have NO accounts in cache.');

                $url = config('enablebanking.url');
                $request = new GetAccountsRequest($url, $sessionId);
                $request->setTimeOut(config('importer.connection.timeout'));

                try {
                    /** @var AccountsResponse $response */
                    $response = $request->get();
                } catch (ImporterHttpException $e) {
                    throw new ImporterErrorException($e->getMessage(), 0, $e);
                }

                $total = count($response);
                Log::debug(sprintf('Found %d Enable Banking accounts.', $total));

                if (0 === $total) {
                    Log::warning('No accounts returned from Enable Banking. For restricted clients, accounts must be pre-authorized in the Enable Banking dashboard.');
                    $messageBag->add('no_accounts', 'No accounts were returned from Enable Banking. If you are using a restricted client, please ensure accounts are pre-authorized in your Enable Banking dashboard.');
                }

                foreach ($response as $index => $account) {
                    Log::debug(sprintf('[%s] [%d/%d] Now collecting information for account %s', config('importer.version'), $index + 1, $total, $account->getUid()));

                    $return[] = $account;
                    $cache[] = $account->toLocalArray();
                }
            }

            Cache::put($cacheKey, $cache, 1800); // half an hour
            $this->importJob->setServiceAccounts($return);
            $this->repository->saveToDisk($this->importJob);
        }

        return $messageBag;
    }

    public function getFlowName(): string
    {
        return 'eb';
    }

    public function validate(): MessageBag
    {
        return new MessageBag();
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
