<?php

/*
 * NewJobDataCollector.php
 * Copyright (c) 2026 open-banking.io contribution to Firefly III (https://github.com/firefly-iii).
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

namespace App\Services\OpenBankingIo\Validation;

use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\OpenBankingIo\Authentication\SecretManager;
use App\Services\OpenBankingIo\Request\GetAccountsRequest;
use App\Services\OpenBankingIo\Response\ErrorResponse;
use App\Services\OpenBankingIo\Response\GetAccountsResponse;
use App\Services\Shared\Validation\NewJobDataCollectorInterface;
use Illuminate\Support\MessageBag;

final class NewJobDataCollector implements NewJobDataCollectorInterface
{
    private ImportJob $importJob;
    private ImportJobRepository $repository;

    public function __construct()
    {
        $this->repository = new ImportJobRepository();
    }

    public function getFlowName(): string
    {
        return 'obio';
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
        $messageBag = new MessageBag();
        $parsed     = SecretManager::parse(SecretManager::getCredentials($this->importJob->getConfiguration()));

        if ('' === $parsed['apiKey'] || '' === $parsed['privateKey']) {
            $messageBag->add('config_file', 'The open-banking.io credentials bundle is missing an API key or private key.');

            return $messageBag;
        }

        $req        = new GetAccountsRequest($parsed['apiKey'], $parsed['privateKey'], $parsed['apiBaseUrl']);
        $req->setTimeOut(config('importer.connection.timeout'));

        /** @var ErrorResponse|GetAccountsResponse $accounts */
        $accounts   = $req->get();

        if ($accounts instanceof ErrorResponse) {
            $message = (string) config(sprintf('importer.http_codes.%d', $accounts->statusCode));
            $messageBag->add('config_file', sprintf('open-banking.io API error with HTTP code %d: %s', $accounts->statusCode, $message));

            return $messageBag;
        }

        foreach ($accounts as $account) {
            $return[] = $account;
        }
        $this->importJob->setServiceAccounts($return);

        return new MessageBag();
    }
}
