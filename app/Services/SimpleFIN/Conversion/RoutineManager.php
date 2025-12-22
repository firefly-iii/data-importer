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
use App\Services\Shared\Conversion\CombinedProgressInformation;
use App\Services\Shared\Conversion\CreatesAccounts;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\SimpleFIN\SimpleFINService;
use Illuminate\Support\Facades\Log;
use Override;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    use CombinedProgressInformation;
    use CreatesAccounts;

    private readonly AccountMapper          $accountMapper;
    private readonly string                 $identifier;
    private readonly SimpleFINService       $simpleFINService;
    private readonly TransactionTransformer $transformer;
    private ImportJob                       $importJob;
    private ImportJobRepository             $repository;

    /**
     * RoutineManager constructor.
     */
    public function __construct($identifier)
    {
        $this->allErrors     = [];
        $this->allWarnings   = [];
        $this->allMessages   = [];
        $this->allRateLimits = [];

        Log::debug('Constructed SimpleFIN RoutineManager');

        $this->identifier       = $identifier;
        $this->simpleFINService = app(SimpleFINService::class);
        $this->transformer      = new TransactionTransformer();
        $this->repository       = new ImportJobRepository();
        $this->importJob        = $this->repository->find($identifier);
        $this->simpleFINService->setConfiguration($this->importJob->getConfiguration());
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    #[Override]
    public function getServiceAccounts(): array
    {
        return $this->importJob->getServiceAccounts();
    }

    /**
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        $this->existingServiceAccounts = $this->getServiceAccounts();
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $transactions  = [];
        $configuration = $this->importJob->getConfiguration();
        $accounts      = $configuration->getAccounts();
        Log::info('Processing SimpleFIN accounts', ['account_count' => count($accounts)]);

        /**
         * @var string $importServiceAccountId
         * @var int $applicationAccountId
         */
        foreach ($accounts as $importServiceAccountId => $applicationAccountId) {
            array_push($transactions, ...$this->processAccount($importServiceAccountId, $applicationAccountId));
        }

        Log::info('SimpleFIN conversion completed', ['total_transactions' => count($transactions)]);

        return $transactions;
    }

    private function processAccount(string $importServiceAccountId, int $applicationAccountId): array
    {
        // Handle account creation if requested (fireflyAccountId === 0 means "create_new")
        if (0 === $applicationAccountId) {
            $this->createNewAccount($importServiceAccountId);
        }
        $currentSimpleFINAccountData = array_find($this->existingServiceAccounts, fn($accountDataFromArrayInLoop) => isset($accountDataFromArrayInLoop['id']) && $accountDataFromArrayInLoop['id'] === $importServiceAccountId);

        if (null === $currentSimpleFINAccountData) {
            Log::warning('Failed to find SimpleFIN account raw data in session for current account ID during transformation. Will redownload.', ['simplefin_account_id_sought' => $importServiceAccountId]);
            Log::debug('Done with downloading new data.');
            // If the account data for this ID isn't found, we can't process its transactions.
            // This might indicate an inconsistency in session data or configuration.
            return [];
        }
        return $this->getTransactions($importServiceAccountId, $currentSimpleFINAccountData);
    }

    private function createNewAccount(string $importServiceAccountId): void
    {
        $configuration                            = $this->importJob->getConfiguration();
        $createdAccount                           = $this->createOrFindExistingAccount($importServiceAccountId);
        $updatedAccounts                          = $configuration->getAccounts();
        $updatedAccounts[$importServiceAccountId] = $createdAccount->id;
        $configuration->setAccounts($updatedAccounts);
        $this->importJob->setConfiguration($configuration);
        $this->repository->saveToDisk($this->importJob);
    }

    private function getTransactions(string $importServiceAccountId, array $simpleFINAccount): array
    {
        Log::debug(sprintf('Extracting transactions for account %s from stored data', $importServiceAccountId));
        $accounts = $this->importJob->getServiceAccounts();
        // Fetch transactions for the current account using the new method signature,
        // passing the complete SimpleFIN accounts data retrieved from the session.
        // Pass the full dataset
        $accountTransactions = $this->simpleFINService->fetchFreshTransactions($importServiceAccountId);
        Log::debug(sprintf('Extracted %d transactions for account %s', count($accountTransactions), $importServiceAccountId));
        $transactions = [];
        // $accountTransactions now contains raw transaction data arrays (from SimpleFIN JSON)
        foreach ($accountTransactions as $transactionData) {
            // Renamed $transactionObject to $transactionData for clarity
            // Use current account mapping (accounts are created immediately, no deferred creation)

            // The transformer now expects:
            // 1. Raw transaction data (array)
            // 2. Parent SimpleFIN account data (array)
            // 3. Full Firefly III account mapping configuration (array)
            // 4. New account configuration data (array) - contains user-provided names
            $transformedTransaction = $this->transformer->transform(
                $transactionData,
                $simpleFINAccount, // The specific SimpleFIN account data for this transaction's parent
                $accounts, // Current mapping with actual account IDs
                $this->importJob->getConfiguration()->getNewAccounts() // User-provided account configuration data
            );

            // Skip zero-amount transactions that transformer filtered out
            if (0 === count($transformedTransaction)) {
                continue;
            }

            // Wrap transaction in group structure expected by Firefly III
            $transactionGroup = [
                'error_if_duplicate_hash' => $this->importJob->getConfiguration()->isIgnoreDuplicateTransactions(),
                'group_title'             => null,
                'transactions'            => [$transformedTransaction]];

            $transactions[] = $transactionGroup;
        }

        return $transactions;
    }
}
