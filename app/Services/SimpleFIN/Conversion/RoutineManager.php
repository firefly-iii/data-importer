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

namespace App\Services\SimpleFIN\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Shared\Conversion\CreatesAccounts;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\SimpleFIN\Model\Account;
use App\Services\SimpleFIN\SimpleFINService;
use Illuminate\Support\Facades\Log;
use Override;

/**
 * Class RoutineManager
 */
final class RoutineManager implements RoutineManagerInterface
{
    use CreatesAccounts;

    private readonly SimpleFINService       $simpleFINService;
    private readonly TransactionTransformer $transformer;
    private ImportJob                       $importJob;
    protected ImportJobRepository           $repository;

    /**
     * RoutineManager constructor.
     */
    public function __construct(ImportJob $importJob)
    {
        Log::debug('Constructed SimpleFIN RoutineManager');
        $this->simpleFINService = app(SimpleFINService::class);
        $this->transformer      = new TransactionTransformer();
        $this->repository       = new ImportJobRepository();
        $this->importJob        = $importJob;
        $this->importJob->refreshInstanceIdentifier();
        $this->simpleFINService->setConfiguration($this->importJob->getConfiguration());
    }

    #[Override]
    public function getServiceAccounts(): array
    {
        Log::debug('Return getServiceAccounts from RoutineManager.');

        return $this->importJob->getServiceAccounts();
    }

    /**
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        $this->existingServiceAccounts = $this->getServiceAccounts();

        if (0 === count($this->existingServiceAccounts)) {
            // retry downloading existing service accounts.
            Log::debug('Do not have service accounts from SimpleFIN, redownload.');
        }

        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $configuration = $this->importJob->getConfiguration();
        $accounts      = $configuration->getAccounts();
        Log::info(sprintf('Processing %d SimpleFIN account(s)', count($accounts)));

        $allAccountIds = [];

        /**
         * @var string $importServiceAccountId
         * @var int $applicationAccountId
         */
        foreach ($accounts as $importServiceAccountId => $applicationAccountId) {
            Log::debug(sprintf('Now testing account "%s": #%d', $importServiceAccountId, $applicationAccountId));
            $res = $this->isValidAccount($importServiceAccountId, $applicationAccountId);
            if ($res) {
                Log::debug(sprintf('Account "%s": #%d is a valid account, will download from.', $importServiceAccountId, $applicationAccountId));
                $allAccountIds[$applicationAccountId] = $importServiceAccountId;
            }
            if (!$res) {
                Log::debug(sprintf('Account "%s": #%d is NOT a valid account, will be skipped.', $importServiceAccountId, $applicationAccountId));
            }
        }
        $transactions = $this->processAccounts($allAccountIds);

        Log::info('SimpleFIN conversion completed', ['total_transactions' => count($transactions)]);

        return $transactions;
    }

    private function isValidAccount(string $importServiceAccountId, int $applicationAccountId): bool
    {
        // Handle account creation if requested (fireflyAccountId === 0 means "create_new")
        if (0 === $applicationAccountId) {
            $this->createNewAccount($importServiceAccountId);
        }

        /** @var null|Account $currentSimpleFINAccountData */
        $currentSimpleFINAccountData = array_find(
            $this->existingServiceAccounts,
            static fn(Account $loopAccount) => $loopAccount->getId() === $importServiceAccountId
        );

        if (null === $currentSimpleFINAccountData) {
            Log::warning('Failed to find SimpleFIN account raw data in session for current account ID during transformation. Will redownload.', [
                'simplefin_account_id_sought' => $importServiceAccountId,
            ]);

            // If the account data for this ID isn't found, we can't process its transactions.
            // This might indicate an inconsistency in session data or configuration.
            return false;
        }
        return true;
    }

    /**
     * @throws ImporterErrorException
     */
    private function processAccounts(array $allAccountIds): array
    {
        Log::debug('Extracting transactions for accounts as listed from stored data', $allAccountIds);
        $accountMapping  = $this->importJob->getConfiguration()->getAccounts();
        $allTransactions = $this->simpleFINService->fetchAllFreshTransactions($allAccountIds);
        $return          = [];

        // Fetch transactions for the current account using the new method signature,
        // passing the complete SimpleFIN accounts data retrieved from the session.
        // Pass the full dataset
        //$accountTransactions = [];
        // Log::debug(sprintf('Extracted %d transactions for account %s', count($accountTransactions), $importServiceAccountId));
        //$transactions        = [];
        // $accountTransactions now contains raw transaction data arrays (from SimpleFIN JSON)

        foreach ($allTransactions as $importServiceAccountId => $transactions) {
            /** @var null|Account $currentSimpleFINAccount */
            $currentSimpleFINAccount = array_find(
                $this->existingServiceAccounts,
                static fn(Account $loopAccount) => $loopAccount->getId() === $importServiceAccountId
            );
            if (null === $currentSimpleFINAccount) {
                Log::error(sprintf('It is quite impossible, but could not find a matching simplefin account for %s', $importServiceAccountId));
                continue;
            }
            foreach ($transactions as $transactionData) {
                $transformedTransaction = $this->transformer->transform(
                    $transactionData,
                    $currentSimpleFINAccount, // The specific SimpleFIN account data for this transaction's parent
                    $accountMapping, // Current mapping with actual account IDs
                    $this->importJob->getConfiguration()->getNewAccounts() // User-provided account configuration data
                );
                // Skip zero-amount transactions that transformer filtered out
                if (0 === count($transformedTransaction)) {
                    Log::error('Filter out empty transaction.');
                    continue;
                }

                // Wrap transaction in group structure expected by Firefly III
                $transactionGroup = [
                    'error_if_duplicate_hash' => $this->importJob->getConfiguration()->isIgnoreDuplicateTransactions(),
                    'apply_rules'             => $this->importJob->getConfiguration()->isRules(),
                    'fire_webhooks'           => $this->importJob->getConfiguration()->isWebhooks(),
                    'group_title'             => null,
                    'transactions'            => [$transformedTransaction],
                ];

                $return[] = $transactionGroup;
            }

        }
        Log::debug(sprintf('Will return %d parsed and processed transactions.', count($return)));
        return $return;
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }
}
