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

use App\Exceptions\ImporterErrorException;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\SimpleFIN\SimpleFINService;
use App\Services\SimpleFIN\Model\Account;
use Illuminate\Support\Str;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    private string $identifier;
    private Configuration $configuration;
    private SimpleFINService $simpleFINService;
    private AccountMapper $accountMapper;
    private TransactionTransformer $transformer;

    /**
     * RoutineManager constructor.
     */
    public function __construct(?string $identifier = null)
    {
        app('log')->debug('Constructed SimpleFIN RoutineManager');

        $this->identifier       = $identifier ?? Str::random(16);
        $this->simpleFINService = app(SimpleFINService::class);
        $this->accountMapper    = new AccountMapper();
        $this->transformer      = new TransactionTransformer($this->accountMapper);
    }

    /**
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        app('log')->debug('Now in SimpleFIN RoutineManager::start()');

        $token                    = session()->get(Constants::SIMPLEFIN_TOKEN); // Retained for general session validation
        $bridgeUrl                = session()->get(Constants::SIMPLEFIN_BRIDGE_URL); // Retained for general session validation
        $allAccountsSimpleFINData = session()->get(
            Constants::SIMPLEFIN_ACCOUNTS_DATA,
            []
        );

        if (
            empty($token)
            || empty($bridgeUrl)
            || empty($allAccountsSimpleFINData)
        ) {
            app('log')->error(
                'SimpleFIN session data incomplete for conversion.',
                [
                    'has_token'         => !empty($token),
                    'has_bridge_url'    => !empty($bridgeUrl),
                    'has_accounts_data' => !empty($allAccountsSimpleFINData),
                ]
            );

            throw new ImporterErrorException(
                'SimpleFIN session data (token, URL, or accounts data) not found or incomplete'
            );
        }

        $transactions             = [];
        $accounts                 = $this->configuration->getAccounts();
        $dateRange                = $this->getDateRange();

        app('log')->info('Processing SimpleFIN accounts', [
            'account_count' => count($accounts),
            'date_range'    => $dateRange,
        ]);

        foreach ($accounts as $simplefinAccountId => $fireflyAccountId) {
            // Handle account creation if requested (fireflyAccountId === 0 means "create_new")
            if (0 === $fireflyAccountId) {
                $newAccountData
                                      = $this->configuration->getNewAccounts()[
                        $simplefinAccountId
                    ] ?? null;
                if (!$newAccountData) {
                    app('log')->error(
                        "No new account data found for SimpleFIN account: {$simplefinAccountId}"
                    );

                    continue;
                }

                // Validate required fields for account creation
                if (empty($newAccountData['name'])) {
                    app('log')->error(
                        "Account name is required for creating SimpleFIN account: {$simplefinAccountId}"
                    );

                    continue;
                }

                // Find the SimpleFIN account data for account creation
                $simplefinAccountData = null;
                foreach ($allAccountsSimpleFINData as $accountData) {
                    if ($accountData['id'] === $simplefinAccountId) {
                        $simplefinAccountData = $accountData;

                        break;
                    }
                }

                if (!$simplefinAccountData) {
                    app('log')->error(
                        "SimpleFIN account data not found for ID: {$simplefinAccountId}"
                    );

                    continue;
                }

                // Prepare account creation configuration with defaults
                $accountConfig        = [
                    'name'     => $newAccountData['name'],
                    'type'     => $newAccountData['type'] ?? 'asset',
                    'currency' => $newAccountData['currency'] ?? 'USD',
                ];

                // Add opening balance if provided
                if (
                    !empty($newAccountData['opening_balance'])
                    && is_numeric($newAccountData['opening_balance'])
                ) {
                    $accountConfig['opening_balance']
                                                           = $newAccountData['opening_balance'];
                    $accountConfig['opening_balance_date'] = date('Y-m-d');
                }

                app('log')->info('Creating new Firefly III account', [
                    'simplefin_account_id' => $simplefinAccountId,
                    'account_config'       => $accountConfig,
                ]);

                // Create SimpleFIN Account object and create Firefly III account
                $simplefinAccount     = Account::fromArray($simplefinAccountData);
                $accountMapper        = new AccountMapper();
                $createdAccount       = $accountMapper->createFireflyAccount(
                    $simplefinAccount,
                    $accountConfig
                );

                if ($createdAccount) {
                    // Account was created immediately - update configuration
                    $fireflyAccountId                     = $createdAccount->id;
                    $updatedAccounts                      = $this->configuration->getAccounts();
                    $updatedAccounts[$simplefinAccountId] = $fireflyAccountId;
                    $this->configuration->setAccounts($updatedAccounts);

                    // CRITICAL: Update local accounts mapping to reflect the new account ID
                    // This ensures TransactionTransformer receives the correct account ID mapping
                    $accounts                             = $this->configuration->getAccounts();

                    app('log')->info(
                        'Successfully created new Firefly III account',
                        [
                            'simplefin_account_id' => $simplefinAccountId,
                            'firefly_account_id'   => $fireflyAccountId,
                            'account_name'         => $createdAccount->name,
                            'account_type'         => $accountConfig['type'],
                            'currency'             => $accountConfig['currency'],
                        ]
                    );

                }
                if (null === $createdAccount) {
                    // Account creation failed - this is a critical error that must be reported
                    $errorMessage = sprintf(
                        'CRITICAL: Failed to create Firefly III account "%s" (type: %s, currency: %s). Cannot proceed with transaction import for this account.',
                        $accountConfig['name'],
                        $accountConfig['type'],
                        $accountConfig['currency']
                    );

                    app('log')->error($errorMessage, [
                        'simplefin_account_id' => $simplefinAccountId,
                        'account_name'         => $accountConfig['name'],
                        'account_type'         => $accountConfig['type'],
                        'currency'             => $accountConfig['currency'],
                    ]);

                    // Throw exception to prevent silent failure - user must be notified
                    throw new ImporterErrorException($errorMessage);
                }
            }

            // Find the specific SimpleFIN account data array for the current $simplefinAccountId.
            // $allAccountsSimpleFINData is an indexed array of account data arrays.
            $currentSimpleFINAccountData = null;
            foreach ($allAccountsSimpleFINData as $accountDataFromArrayInLoop) {
                if (
                    isset($accountDataFromArrayInLoop['id'])
                    && $accountDataFromArrayInLoop['id'] === $simplefinAccountId
                ) {
                    $currentSimpleFINAccountData = $accountDataFromArrayInLoop;

                    break;
                }
            }

            if (null === $currentSimpleFINAccountData) {
                app('log')->error(
                    'Failed to find SimpleFIN account raw data in session for current account ID during transformation.',
                    ['simplefin_account_id_sought' => $simplefinAccountId]
                );

                // If the account data for this ID isn't found, we can't process its transactions.
                // This might indicate an inconsistency in session data or configuration.
                continue; // Skip to the next account in $accounts.
            }

            try {
                app('log')->debug(
                    "Extracting transactions for account {$simplefinAccountId} from stored data"
                );

                // Fetch transactions for the current account using the new method signature,
                // passing the complete SimpleFIN accounts data retrieved from the session.
                $accountTransactions = $this->simpleFINService->fetchTransactions(
                    $allAccountsSimpleFINData, // Pass the full dataset
                    $simplefinAccountId,
                    $dateRange
                );

                app('log')->debug(
                    "Extracted {count} transactions for account {$simplefinAccountId}",
                    ['count' => count($accountTransactions)]
                );

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
                        if (empty($transformedTransaction)) {
                            continue;
                        }

                        // Wrap transaction in group structure expected by Firefly III
                        $transactionGroup             = [
                            'group_title'  => $transformedTransaction['description']
                                ?? 'SimpleFIN Transaction',
                            'transactions' => [$transformedTransaction],
                        ];

                        $transactions[]               = $transactionGroup;
                    } catch (ImporterErrorException $e) {
                        app('log')->warning(
                            'Transaction transformation failed for a specific transaction.',
                            [
                                'simplefin_account_id' => $simplefinAccountId,
                                'transaction_id'       => isset($transactionData['id'])
                                    && is_scalar($transactionData['id'])
                                        ? (string)$transactionData['id']
                                        : 'unknown',
                                'error'                => $e->getMessage(),
                                // Avoid logging full $transactionData unless necessary for deep debug, could be large/sensitive.
                            ]
                        );
                    }
                }
            } catch (ImporterErrorException $e) {
                app('log')->error('Failed to fetch transactions for account', [
                    'account' => $simplefinAccountId,
                    'error'   => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        app('log')->info('SimpleFIN conversion completed', [
            'total_transactions' => count($transactions),
        ]);

        return $transactions;
    }

    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    #[\Override]
    public function getServiceAccounts(): array
    {
        return session()->get(Constants::SIMPLEFIN_ACCOUNTS_DATA, []);
    }

    /**
     * Get date range for transaction fetching
     */
    private function getDateRange(): array
    {
        $dateAfter  = $this->configuration->getDateNotBefore();
        $dateBefore = $this->configuration->getDateNotAfter();

        return [
            'start' => !empty($dateAfter) ? $dateAfter : null,
            'end'   => !empty($dateBefore) ? $dateBefore : null,
        ];
    }
}
