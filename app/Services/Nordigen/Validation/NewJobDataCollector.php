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

namespace App\Services\Nordigen\Validation;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Nordigen\Model\Account as NordigenAccount;
use App\Services\Nordigen\Request\ListAccountsRequest;
use App\Services\Nordigen\Response\ListAccountsResponse;
use App\Services\Nordigen\Services\AccountInformationCollector;
use App\Services\Nordigen\TokenManager;
use App\Services\Shared\Validation\NewJobDataCollectorInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;

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
        $this->importJob->refreshInstanceIdentifier();
        $messageBag    = new MessageBag();
        $configuration = $this->importJob->getConfiguration();
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $requisitions = $configuration->getNordigenRequisitions();
        $return       = [];
        $cache        = [];
        if (0 === count($requisitions)) {
            $messageBag->add('missing_requisitions', 'true');
            return $messageBag;
        }
        foreach ($requisitions as $requisition) {
            $inCache = Cache::has($requisition) && config('importer.use_cache');
            // if cached, return it.
            if ($inCache) {
                $result = Cache::get($requisition);
                foreach ($result as $arr) {
                    $return[] = NordigenAccount::fromArray($arr);
                }
                Log::debug('Grab accounts from cache', $result);
            }
            if (!$inCache) {
                // get banks and countries
                $accessToken = TokenManager::getAccessToken();
                $url         = config('nordigen.url');
                $request     = new ListAccountsRequest($url, $requisition, $accessToken);
                $request->setTimeOut(config('importer.connection.timeout'));

                /** @var ListAccountsResponse $response */
                try {
                    $response = $request->get();
                } catch (ImporterErrorException|ImporterHttpException $e) {
                    throw new ImporterErrorException($e->getMessage(), 0, $e);
                }
                $total = count($response);
                Log::debug(sprintf('Found %d GoCardless accounts.', $total));

                /** @var NordigenAccount $account */
                foreach ($response as $index => $account) {
                    Log::debug(sprintf('[%s] [%d/%d] Now collecting information for account %s', config('importer.version'), $index + 1, $total, $account->getIdentifier()), $account->toLocalArray());
                    $account  = AccountInformationCollector::collectInformation($account, true);
                    $return[] = $account;
                    $cache[]  = $account->toLocalArray();
                }
            }
            Cache::put($requisition, $cache, 1800); // half an hour
            $this->importJob->setServiceAccounts($return);
            $this->repository->saveToDisk($this->importJob);
        }
        return $messageBag;
    }

    public function getFlowName(): string
    {
        return 'nordigen';
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
