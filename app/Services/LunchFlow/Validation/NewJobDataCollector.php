<?php

declare(strict_types=1);
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

namespace App\Services\LunchFlow\Validation;

use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\LunchFlow\Authentication\SecretManager as LunchFlowSecretManager;
use App\Services\LunchFlow\Request\GetAccountsRequest as LunchFlowGetAccountsRequest;
use App\Services\LunchFlow\Response\ErrorResponse;
use App\Services\Shared\Validation\NewJobDataCollectorInterface;
use App\Services\Spectre\Response\GetAccountsResponse;
use Illuminate\Support\MessageBag;

class NewJobDataCollector implements NewJobDataCollectorInterface
{
    private ImportJob           $importJob;
    private ImportJobRepository $repository;

    public function __construct()
    {
        $this->repository = new ImportJobRepository();
    }

    public function getFlowName(): string
    {
        return 'lunchflow';
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function setImportJob(ImportJob $importJob): void
    {
        $importJob->refreshInstanceIdentifier();
        $this->importJob = $importJob;
    }

    public function validate(): MessageBag
    {
        return new MessageBag();
    }

    public function collectAccounts(): MessageBag
    {
        $return     = [];
        $url        = config('lunchflow.api_url');
        $apiKey     = LunchFlowSecretManager::getApiKey($this->importJob->getConfiguration());
        $messageBag = new MessageBag();
        $req        = new LunchFlowGetAccountsRequest($apiKey);
        $req->setTimeOut(config('importer.connection.timeout'));

        /** @var ErrorResponse|GetAccountsResponse $accounts */
        $accounts   = $req->get();

        if ($accounts instanceof ErrorResponse) {
            $message = (string)config(sprintf('importer.http_codes.%d', $accounts->statusCode));
            $messageBag->add('config_file', sprintf('LunchFlow API error with HTTP code %d: %s', $accounts->statusCode, $message));

            return $messageBag;
        }

        foreach ($accounts as $account) {
            $return[] = $account;
        }
        $this->importJob->setServiceAccounts($return);

        return new MessageBag();
    }
}
