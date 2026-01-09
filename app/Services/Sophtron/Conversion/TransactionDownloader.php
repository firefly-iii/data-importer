<?php

declare(strict_types=1);
/*
 * TransactionDownloader.php
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
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Conversion\CreatesAccounts;
use App\Services\Sophtron\Model\Transaction;
use App\Services\Sophtron\Request\PostGetTransactionsByTransactionDateRequest;
use App\Services\Sophtron\Validation\NewJobDataCollector;
use Illuminate\Support\Facades\Log;

class TransactionDownloader
{
    use CreatesAccounts;

    private ImportJob           $importJob;
    private ImportJobRepository $repository;

    public function __construct(ImportJob $importJob)
    {
        $importJob->refreshInstanceIdentifier();
        $this->importJob               = $importJob;
        $this->repository              = new ImportJobRepository();
        $this->existingServiceAccounts = $importJob->getServiceAccounts();
    }

    public function download(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $transactions  = [];
        $configuration = $this->importJob->getConfiguration();
        $accounts      = $configuration->getAccounts();
        Log::debug(sprintf('Processing %d Sophtron account(s)', count($accounts)));

        // need to download or grab service accounts, so the data can be used to create new accounts.
        if (0 === count($this->importJob->getServiceAccounts())) {
            Log::debug('Import job has no Sophtron accounts, will redownload them.');
            $collector                     = new NewJobDataCollector();
            $collector->setImportJob($this->importJob);
            $collector->downloadInstitutionsByUser();
            $this->importJob               = $collector->getImportJob();
            $this->existingServiceAccounts = $this->importJob->getServiceAccounts();
        }

        /**
         * @var string $importServiceAccountId
         * @var int    $applicationAccountId
         */
        foreach ($accounts as $importServiceAccountId => $applicationAccountId) {
            Log::debug(sprintf('Now processing account "%s": #%d', $importServiceAccountId, $applicationAccountId));
            array_push($transactions, ...$this->processAccount($importServiceAccountId, $applicationAccountId));
        }

        Log::debug('Conversion completed', ['total_transactions' => count($transactions)]);

        return $transactions;
    }

    private function processAccount(string $importServiceAccountId, int $applicationAccountId): array
    {
        Log::debug(sprintf('Processing account "%s"', $importServiceAccountId));
        // Handle account creation if requested (fireflyAccountId === 0 means "create_new")
        if (0 === $applicationAccountId) {
            $this->createNewAccount($importServiceAccountId);
        }

        return $this->getTransactions($importServiceAccountId);
    }

    private function getTransactions(string $accountId): array
    {
        $userId        = SecretManager::getSophtronUserId($this->importJob);
        $accessKey     = SecretManager::getSophtronAccessKey($this->importJob);

        // before and after dates:
        $configuration = $this->importJob->getConfiguration();
        $configuration->updateDateRange();
        $notBefore     = $configuration->getDateNotBefore();
        $notAfter      = $configuration->getDateNotAfter();

        $request       = new PostGetTransactionsByTransactionDateRequest($userId, $accessKey, $accountId, $notBefore, $notAfter);
        $response      = $request->post();
        $return        = [];

        /** @var Transaction $transaction */
        foreach ($response as $transaction) {
            $return[] = $transaction->toArray();
        }

        return $return;
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }
}
