<?php

/*
 * RoutineManager.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
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

use App\Services\Shared\Conversion\CombinedProgressInformation;
use Carbon\Carbon;
use App\Exceptions\ImporterErrorException;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\SimpleFIN\Model\Account;
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
    private readonly AccountMapper          $accountMapper;
    private Configuration          $configuration;
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

        $this->identifier       = $identifier ?? Str::random(16);
        $this->simpleFINService = app(SimpleFINService::class);
        $this->accountMapper    = new AccountMapper();
        $this->transformer      = new TransactionTransformer($this->accountMapper);
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    #[Override]
    public function getServiceAccounts(): array
    {
        return session()->get(Constants::SIMPLEFIN_ACCOUNTS_DATA, []);
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
        Log::debug('Now in SimpleFIN RoutineManager::start()');

        $token                    = (string)session()->get(Constants::SIMPLEFIN_TOKEN); // Retained for general session validation
        $bridgeUrl                = (string)session()->get(Constants::SIMPLEFIN_BRIDGE_URL); // Retained for general session validation
        $allAccountsSimpleFINData = session()->get(Constants::SIMPLEFIN_ACCOUNTS_DATA, []);
        $accessToken              = $this->configuration->getAccessToken();

        if ('' === $accessToken && ('' === $token || '' === $bridgeUrl || 0 === count($allAccountsSimpleFINData))) {
            Log::error(
                'SimpleFIN session data incomplete for conversion.',
                [
                    'access_token'      => '' !== $accessToken,
                    'has_token'         => '' !== $token,
                    'has_bridge_url'    => '' !== $bridgeUrl,
                    'has_accounts_data' => 0 !== count($allAccountsSimpleFINData),
                ]
            );

            throw new ImporterErrorException('SimpleFIN session data (token, URL, or accounts data) not found or incomplete');
        }

        $transactions             = [];
        $accounts                 = $this->configuration->getAccounts();
        $dateRange                = $this->getDateRange();

        Log::info('Processing SimpleFIN accounts', ['account_count' => count($accounts), 'date_range' => $dateRange]);

        foreach ($accounts as $simplefinAccountId => $fireflyAccountId) {
            // Handle account creation if requested (fireflyAccountId === 0 means "create_new")
            if (0 === $fireflyAccountId) {
                $newAccountData       = $this->configuration->getNewAccounts()[$simplefinAccountId] ?? null;
                if (!$newAccountData) {
                    Log::error(sprintf('No new account data found for SimpleFIN account: %s', $simplefinAccountId));

                    continue;
                }

                // Validate required fields for account creation
                if ('' === (string)$newAccountData['name']) {
                    Log::error("Account name is required for creating SimpleFIN account: {$simplefinAccountId}");

                    continue;
                }
                $simplefinAccountData = array_find($allAccountsSimpleFINData, fn ($accountData) => $accountData['id'] === $simplefinAccountId);

                if (!$simplefinAccountData) {
                    Log::error("SimpleFIN account data not found for ID: {$simplefinAccountId}");

                    continue;
                }

                // Prepare account creation configuration with defaults
                $accountConfig        = [
                    'name'     => $newAccountData['name'],
                    'type'     => $newAccountData['type'] ?? 'asset',
                    'currency' => $newAccountData['currency'] ?? 'EUR',
                ];

                // Add opening balance if provided
                if ('' !== (string) $newAccountData['opening_balance'] && is_numeric($newAccountData['opening_balance'])) {
                    $accountConfig['opening_balance']      = $newAccountData['opening_balance'];
                    $accountConfig['opening_balance_date'] = Carbon::now()->format('Y-m-d');
                }

                Log::info('Creating new Firefly III account', ['simplefin_account_id' => $simplefinAccountId, 'account_config' => $accountConfig]);

                // Create SimpleFIN Account object and create Firefly III account
                $simplefinAccount     = Account::fromArray($simplefinAccountData);
                $accountMapper        = new AccountMapper();
                $createdAccount       = $accountMapper->createFireflyAccount($simplefinAccount, $accountConfig);

                if ($createdAccount instanceof \GrumpyDictator\FFIIIApiSupport\Model\Account) {
                    // Account was created immediately - update configuration
                    $fireflyAccountId                     = $createdAccount->id;
                    $updatedAccounts                      = $this->configuration->getAccounts();
                    $updatedAccounts[$simplefinAccountId] = $fireflyAccountId;
                    $this->configuration->setAccounts($updatedAccounts);

                    // CRITICAL: Update local accounts mapping to reflect the new account ID
                    // This ensures TransactionTransformer receives the correct account ID mapping
                    $accounts                             = $this->configuration->getAccounts();

                    Log::info('Successfully created new Firefly III account', ['simplefin_account_id' => $simplefinAccountId, 'firefly_account_id' => $fireflyAccountId, 'account_name' => $createdAccount->name, 'account_type' => $accountConfig['type'], 'currency' => $accountConfig['currency']]);

                }
                if (!$createdAccount instanceof \GrumpyDictator\FFIIIApiSupport\Model\Account) {
                    // Account creation failed - this is a critical error that must be reported
                    $errorMessage   = sprintf('Failed to create Firefly III account "%s" (type: %s, currency: %s). Cannot proceed with transaction import for this account.', $accountConfig['name'], $accountConfig['type'], $accountConfig['currency']);

                    Log::warning($errorMessage, ['simplefin_account_id' => $simplefinAccountId, 'account_name' => $accountConfig['name'], 'account_type' => $accountConfig['type'], 'currency' => $accountConfig['currency']]);

                    // try to find a matching account.
                    $createdAccount = $accountMapper->findMatchingFireflyAccount($simplefinAccount);
                    if (!$createdAccount instanceof \GrumpyDictator\FFIIIApiSupport\Model\Account) {
                        Log::error('Could also not find a matching account for SimpleFIN account.', $simplefinAccount);

                        throw new ImporterErrorException($errorMessage);
                    }
                }
            }
            $currentSimpleFINAccountData = array_find($allAccountsSimpleFINData, fn ($accountDataFromArrayInLoop) => isset($accountDataFromArrayInLoop['id']) && $accountDataFromArrayInLoop['id'] === $simplefinAccountId);

            if (null === $currentSimpleFINAccountData) {
                Log::warning('Failed to find SimpleFIN account raw data in session for current account ID during transformation. Will redownload.', ['simplefin_account_id_sought' => $simplefinAccountId]);
                $allAccountsSimpleFINData    = $this->simpleFINService->fetchAccounts();
                $currentSimpleFINAccountData = array_find($allAccountsSimpleFINData, fn ($accountDataFromArrayInLoop) => isset($accountDataFromArrayInLoop['id']) && $accountDataFromArrayInLoop['id'] === $simplefinAccountId);
                Log::debug('Done with downloading new data.');
                // If the account data for this ID isn't found, we can't process its transactions.
                // This might indicate an inconsistency in session data or configuration.
                // continue; // Skip to the next account in $accounts.
            }

            try {
                Log::debug(sprintf('Extracting transactions for account %s from stored data', $simplefinAccountId));

                // Fetch transactions for the current account using the new method signature,
                // passing the complete SimpleFIN accounts data retrieved from the session.
                // Pass the full dataset
                // $accountTransactions = $this->simpleFINService->fetchTransactions($allAccountsSimpleFINData, $simplefinAccountId, $dateRange);
                $accountTransactions = $this->simpleFINService->fetchFreshTransactions($simplefinAccountId, $dateRange);

                Log::debug(sprintf('Extracted %d transactions for account %s', count($accountTransactions), $simplefinAccountId));

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
                        Log::warning('Transaction transformation failed for a specific transaction.', ['simplefin_account_id' => $simplefinAccountId, 'transaction_id' => isset($transactionData['id']) && is_scalar($transactionData['id']) ? (string)$transactionData['id'] : 'unknown', 'error' => $e->getMessage(), // Avoid logging full $transactionData unless necessary for deep debug, could be large/sensitive.
                        ]);
                    }
                }
            } catch (ImporterErrorException $e) {
                Log::error('Failed to fetch transactions for account', ['account' => $simplefinAccountId, 'error' => $e->getMessage()]);

                throw $e;
            }
        }

        Log::info('SimpleFIN conversion completed', ['total_transactions' => count($transactions)]);

        return $transactions;
    }

    /**
     * Get date range for transaction fetching
     */
    private function getDateRange(): array
    {
        $dateAfter  = $this->configuration->getDateNotBefore();
        $dateBefore = $this->configuration->getDateNotAfter();

        return [
            'start' => '' !== $dateAfter ? $dateAfter : null,
            'end'   => '' !== $dateBefore ? $dateBefore : null,
        ];
    }
}
