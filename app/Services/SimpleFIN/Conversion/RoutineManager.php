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
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\CombinedProgressInformation;
use App\Services\Shared\Conversion\CreatesAccounts;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\SimpleFIN\SimpleFINService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Override;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    use CombinedProgressInformation;
    use CreatesAccounts;

    private readonly AccountMapper          $accountMapper;
    private Configuration                   $configuration;
    private readonly string                 $identifier;
    private readonly SimpleFINService       $simpleFINService;
    private readonly TransactionTransformer $transformer;

    /**
     * RoutineManager constructor.
     */
    public function __construct(?string $identifier = null)
    {
        $this->allErrors        = [];
        $this->allWarnings      = [];
        $this->allMessages      = [];
        $this->allRateLimits    = [];

        Log::debug('Constructed SimpleFIN RoutineManager');

        $this->identifier       = $identifier ?? Str::random(); // 16
        $this->simpleFINService = app(SimpleFINService::class);
        $this->transformer      = new TransactionTransformer();
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    #[Override]
    public function getServiceAccounts(): array
    {
        $sessionData = session()->get(Constants::SIMPLEFIN_ACCOUNTS_DATA, []);
        if (0 === count($sessionData)) {
            return $this->simpleFINService->fetchAccounts();
        }

        return $sessionData;
    }

    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
        $this->simpleFINService->setConfiguration($configuration);
    }

    /**
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        $this->existingServiceAccounts = $this->getServiceAccounts();
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $token                         = (string)session()->get(Constants::SIMPLEFIN_TOKEN); // Retained for general session validation
        $accessToken                   = $this->configuration->getAccessToken();

        if ('' === $accessToken && ('' === $token || 0 === count($this->existingServiceAccounts))) {
            Log::error(
                'SimpleFIN data incomplete for conversion.',
                [
                    'access_token'      => '' !== $accessToken,
                    'has_token'         => '' !== $token,
                    'has_accounts_data' => 0 !== count($this->existingServiceAccounts),
                ]
            );

            throw new ImporterErrorException('SimpleFIN session data (token, URL, or accounts data) not found or incomplete');
        }
        $transactions                  = [];
        $accounts                      = $this->configuration->getAccounts();

        Log::info('Processing SimpleFIN accounts', ['account_count' => count($accounts)]);

        foreach ($accounts as $importServiceAccountId => $fireflyAccountId) {
            // Handle account creation if requested (fireflyAccountId === 0 means "create_new")
            if (0 === $fireflyAccountId) {
                $createdAccount                           = $this->createOrFindExistingAccount($importServiceAccountId);
                $updatedAccounts                          = $this->configuration->getAccounts();
                $updatedAccounts[$importServiceAccountId] = $createdAccount->id;
                $this->configuration->setAccounts($updatedAccounts);
                $accounts                                 = $this->configuration->getAccounts();
            }
            $currentSimpleFINAccountData = array_find($this->existingServiceAccounts, fn ($accountDataFromArrayInLoop) => isset($accountDataFromArrayInLoop['id']) && $accountDataFromArrayInLoop['id'] === $importServiceAccountId);

            if (null === $currentSimpleFINAccountData) {
                Log::warning('Failed to find SimpleFIN account raw data in session for current account ID during transformation. Will redownload.', ['simplefin_account_id_sought' => $importServiceAccountId]);
                $allAccountsSimpleFINData    = $this->simpleFINService->fetchAccounts();
                $currentSimpleFINAccountData = array_find($allAccountsSimpleFINData, fn ($accountDataFromArrayInLoop) => isset($accountDataFromArrayInLoop['id']) && $accountDataFromArrayInLoop['id'] === $importServiceAccountId);
                Log::debug('Done with downloading new data.');
                // If the account data for this ID isn't found, we can't process its transactions.
                // This might indicate an inconsistency in session data or configuration.
                // continue; // Skip to the next account in $accounts.
            }

            try {
                Log::debug(sprintf('Extracting transactions for account %s from stored data', $importServiceAccountId));

                // Fetch transactions for the current account using the new method signature,
                // passing the complete SimpleFIN accounts data retrieved from the session.
                // Pass the full dataset
                // $accountTransactions = $this->simpleFINService->fetchTransactions($allAccountsSimpleFINData, $simplefinAccountId, $dateRange);
                $accountTransactions = $this->simpleFINService->fetchFreshTransactions($importServiceAccountId);

                Log::debug(sprintf('Extracted %d transactions for account %s', count($accountTransactions), $importServiceAccountId));

                // $accountTransactions now contains raw transaction data arrays (from SimpleFIN JSON)
                foreach ($accountTransactions as $transactionData) {
                    // Renamed $transactionObject to $transactionData for clarity
                    try {
                        // Use current account mapping (accounts are created immediately, no deferred creation)
                        $accountMappingForTransformer = $accounts;


                        // The transformer now expects:
                        // 1. Raw transaction data (array)
                        // 2. Parent SimpleFIN account data (array)
                        // 3. Full Firefly III account mapping configuration (array)
                        // 4. New account configuration data (array) - contains user-provided names
                        $transformedTransaction       = $this->transformer->transform(
                            $transactionData,
                            $currentSimpleFINAccountData, // The specific SimpleFIN account data for this transaction's parent
                            $accountMappingForTransformer, // Current mapping with actual account IDs
                            $this->configuration->getNewAccounts() // User-provided account configuration data
                        );


                        // Skip zero-amount transactions that transformer filtered out
                        if (0 === count($transformedTransaction)) {
                            continue;
                        }

                        // Wrap transaction in group structure expected by Firefly III
                        $transactionGroup             = [
                            'error_if_duplicate_hash' => $this->configuration->isIgnoreDuplicateTransactions(),
                            'group_title'             => null,
                            'transactions'            => [$transformedTransaction]];

                        $transactions[]               = $transactionGroup;
                    } catch (ImporterErrorException $e) {
                        Log::warning('Transaction transformation failed for a specific transaction.', ['simplefin_account_id' => $importServiceAccountId, 'transaction_id' => isset($transactionData['id']) && is_scalar($transactionData['id']) ? (string)$transactionData['id'] : 'unknown', 'error' => $e->getMessage(), // Avoid logging full $transactionData unless necessary for deep debug, could be large/sensitive.
                        ]);
                    }
                }
            } catch (ImporterErrorException $e) {
                Log::error('Failed to fetch transactions for account', ['account' => $importServiceAccountId, 'error' => $e->getMessage()]);

                throw $e;
            }
        }

        Log::info('SimpleFIN conversion completed', ['total_transactions' => count($transactions)]);

        return $transactions;
    }
}
