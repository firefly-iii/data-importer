<?php

/*
 * AccountMapper.php
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
use App\Services\Shared\Authentication\SecretManager;
use App\Services\SimpleFIN\Model\Account as SimpleFINAccount;
use App\Services\SimpleFIN\Request\PostAccountRequest;
use App\Services\SimpleFIN\Response\PostAccountResponse;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Model\AccountType;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use GrumpyDictator\FFIIIApiSupport\Request\GetSearchAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountsResponse;
use GrumpyDictator\FFIIIApiSupport\Response\Response;
use GrumpyDictator\FFIIIApiSupport\Response\ValidationErrorResponse;

/**
 * Class AccountMapper
 */
class AccountMapper
{
    private array $fireflyAccounts = [];
    private array $accountMapping = [];
    private array $createdAccounts = [];

    public function __construct()
    {
        // Defer account loading until actually needed to avoid authentication errors
        // during constructor when authentication context may not be available
    }

    /**
     * Map SimpleFIN accounts to Firefly III accounts
     * @param array $simplefinAccounts
     * @param array $configuration
     * @return array
     */
    public function mapAccounts(array $simplefinAccounts, array $configuration = []): array
    {
        $mapping = [];

        foreach ($simplefinAccounts as $simplefinAccount) {
            if (!$simplefinAccount instanceof SimpleFINAccount) {
                continue;
            }

            $accountKey = $simplefinAccount->getId();

            // Check if mapping is already configured
            if (isset($configuration['account_mapping'][$accountKey])) {
                $mappingConfig = $configuration['account_mapping'][$accountKey];

                if ($mappingConfig['action'] === 'map' && isset($mappingConfig['firefly_account_id'])) {
                    // Map to existing account
                    $fireflyAccount = $this->getFireflyAccountById((int) $mappingConfig['firefly_account_id']);
                    if ($fireflyAccount) {
                        $mapping[$accountKey] = [
                            'simplefin_account' => $simplefinAccount,
                            'firefly_account_id' => $fireflyAccount->id,
                            'firefly_account_name' => $fireflyAccount->name,
                            'action' => 'map',
                        ];
                    }
                } elseif ($mappingConfig['action'] === 'create') {
                    // Create new account
                    $fireflyAccount = $this->createFireflyAccount($simplefinAccount, $mappingConfig);
                    if ($fireflyAccount) {
                        $mapping[$accountKey] = [
                            'simplefin_account' => $simplefinAccount,
                            'firefly_account_id' => $fireflyAccount->id,
                            'firefly_account_name' => $fireflyAccount->name,
                            'action' => 'create',
                        ];
                    }
                }
            } else {
                // Auto-map by searching for existing accounts
                $fireflyAccount = $this->findMatchingFireflyAccount($simplefinAccount);
                if ($fireflyAccount) {
                    $mapping[$accountKey] = [
                        'simplefin_account' => $simplefinAccount,
                        'firefly_account_id' => $fireflyAccount->id,
                        'firefly_account_name' => $fireflyAccount->name,
                        'action' => 'auto_map',
                    ];
                } else {
                    // No mapping found - will need user input
                    $mapping[$accountKey] = [
                        'simplefin_account' => $simplefinAccount,
                        'firefly_account_id' => null,
                        'firefly_account_name' => null,
                        'action' => 'unmapped',
                    ];
                }
            }
        }

        return $mapping;
    }

    /**
     * Get available Firefly III accounts for mapping
     */
    public function getAvailableFireflyAccounts(): array
    {
        $this->loadFireflyAccounts();
        return $this->fireflyAccounts;
    }

    /**
     * Find a matching Firefly III account for a SimpleFIN account
     */
    private function findMatchingFireflyAccount(SimpleFINAccount $simplefinAccount): ?Account
    {
        $this->loadFireflyAccounts();

        // Try to find by name first
        $matchingAccounts = array_filter($this->fireflyAccounts, function (Account $account) use ($simplefinAccount) {
            return strtolower($account->name) === strtolower($simplefinAccount->getName());
        });

        if (!empty($matchingAccounts)) {
            return reset($matchingAccounts);
        }

        // Try to search via API
        try {
            $request = new GetSearchAccountRequest(SecretManager::getBaseUrl(), SecretManager::getAccessToken());
            $request->setQuery($simplefinAccount->getName());
            $response = $request->get();

            if ($response instanceof GetAccountsResponse && count($response) > 0) {
                foreach ($response as $account) {
                    if (strtolower($account->name) === strtolower($simplefinAccount->getName())) {
                        return $account;
                    }
                }
            }
        } catch (ApiHttpException $e) {
            app('log')->warning(sprintf('Could not search for account "%s": %s', $simplefinAccount->getName(), $e->getMessage()));
        }

        return null;
    }

    /**
     * Create account immediately via Firefly III API
     * @param SimpleFINAccount $simplefinAccount
     * @param array $config
     * @return Account|null
     */
    public function createFireflyAccount(SimpleFINAccount $simplefinAccount, array $config): ?Account
    {
        $accountName = $config['name'] ?? $simplefinAccount->getName();
        $accountType = $this->determineAccountType($simplefinAccount, $config);
        $currencyCode = $this->getCurrencyCode($simplefinAccount, $config);
        $openingBalance = $config['opening_balance'] ?? '0.00';

        app('log')->info(sprintf('Creating Firefly III account "%s" immediately via API', $accountName));

        try {
            $request = new PostAccountRequest(SecretManager::getBaseUrl(), SecretManager::getAccessToken());

            // Build account creation payload
            $payload = [
                'name' => $accountName,
                'type' => $accountType,
                'currency_code' => $currencyCode,
                'opening_balance' => $openingBalance,
                'active' => true,
                'include_net_worth' => true,
            ];

            // Add opening balance date if opening balance is provided
            if (!empty($config['opening_balance']) && is_numeric($config['opening_balance'])) {
                $payload['opening_balance_date'] = $config['opening_balance_date'] ?? date('Y-m-d');
            }

            // Add account role for asset accounts
            if ($accountType === AccountType::ASSET) {
                $payload['account_role'] = $config['account_role'] ?? 'defaultAsset';
            }

            // Add liability-specific fields for liability accounts
            if (in_array($accountType, [AccountType::DEBT, AccountType::LOAN, AccountType::MORTGAGE, AccountType::LIABILITIES, 'liability'], true)) {
                // Map account type to liability type
                $liabilityTypeMap = [
                    AccountType::DEBT => 'debt',
                    AccountType::LOAN => 'loan',
                    AccountType::MORTGAGE => 'mortgage',
                    AccountType::LIABILITIES => 'debt', // Default generic liabilities to debt
                    'liability' => 'debt', // Handle user-provided 'liability' type
                ];

                $payload['liability_type'] = $config['liability_type'] ?? $liabilityTypeMap[$accountType] ?? 'debt';
                $payload['liability_direction'] = $config['liability_direction'] ?? 'credit';
            }

            // Add IBAN if provided
            if (!empty($config['iban'])) {
                $payload['iban'] = $config['iban'];
            }

            // Add account number if provided
            if (!empty($config['account_number'])) {
                $payload['account_number'] = $config['account_number'];
            }

            $request->setBody($payload);
            $response = $this->makeApiCallWithRetry($request, $accountName);

            if ($response instanceof ValidationErrorResponse) {
                app('log')->error(sprintf('Failed to create account "%s": %s', $accountName, json_encode($response->errors->toArray())));
                return null;
            }

            if ($response instanceof PostAccountResponse) {
                $account = $response->getAccount();
                if ($account) {
                    app('log')->info(sprintf('Successfully created account "%s" with ID %d', $accountName, $account->id));

                    // Add to our local cache
                    $this->fireflyAccounts[] = $account;
                    $this->createdAccounts[] = $account;

                    return $account;
                }
            }

            app('log')->error(sprintf('Unexpected response type when creating account "%s"', $accountName));
            return null;

        } catch (ApiHttpException $e) {
            app('log')->error(sprintf('API error creating account "%s": %s', $accountName, $e->getMessage()));
            return null;
        } catch (\Exception $e) {
            app('log')->error(sprintf('Unexpected error creating account "%s": %s', $accountName, $e->getMessage()));
            return null;
        }
    }

    /**
     * Determine the appropriate Firefly III account type
     * @param SimpleFINAccount $simplefinAccount
     * @param array $config
     * @return string
     */
    private function determineAccountType(SimpleFINAccount $simplefinAccount, array $config): string
    {
        if (isset($config['type'])) {
            return $config['type'];
        }

        // Default to asset account for most SimpleFIN accounts
        return AccountType::ASSET;
    }

    /**
     * Get currency code for account creation
     */
    private function getCurrencyCode(SimpleFINAccount $simplefinAccount, array $config): string
    {
        // 1. Use user-configured currency first
        if (!empty($config['currency'])) {
            return $config['currency'];
        }

        // 2. Fall back to SimpleFIN account currency
        $currency = $simplefinAccount->getCurrency();
        if ($simplefinAccount->isCustomCurrency()) {
            // For custom currencies, default to user's base currency or USD
            return 'USD'; // Could be made configurable
        }

        // 3. Final fallback
        return $currency ?: 'USD';
    }

    /**
     * Get Firefly III account by ID
     */
    private function getFireflyAccountById(int $id): ?Account
    {
        $this->loadFireflyAccounts();

        foreach ($this->fireflyAccounts as $account) {
            if ($account->id === $id) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Load all Firefly III accounts
     */
    private function loadFireflyAccounts(): void
    {
        // Only load once
        if (!empty($this->fireflyAccounts)) {
            return;
        }

        try {
            // Verify authentication context before making API calls
            $baseUrl = SecretManager::getBaseUrl();
            $accessToken = SecretManager::getAccessToken();

            if (empty($baseUrl) || empty($accessToken)) {
                app('log')->warning('Missing authentication context for Firefly III account loading');
                throw new ImporterErrorException('Authentication context not available for account loading');
            }

            $request = new GetAccountsRequest($baseUrl, $accessToken);
            $request->setType(AccountType::ASSET);
            $response = $request->get();

            if ($response instanceof GetAccountsResponse) {
                $this->fireflyAccounts = iterator_to_array($response);
                app('log')->debug(sprintf('Loaded %d Firefly III accounts', count($this->fireflyAccounts)));
            }
        } catch (ApiHttpException $e) {
            app('log')->error(sprintf('Could not load Firefly III accounts: %s', $e->getMessage()));
            throw new ImporterErrorException(sprintf('Could not load Firefly III accounts: %s', $e->getMessage()));
        }
    }

    /**
     * Make API call with DNS resilience retry pattern
     * @param PostAccountRequest $request
     * @param string $accountName
     * @return Response
     * @throws ApiHttpException
     */
    private function makeApiCallWithRetry(PostAccountRequest $request, string $accountName): Response
    {
        $retryDelays = [0, 2, 5]; // immediate, 2s delay, 5s delay
        $lastException = null;

        foreach ($retryDelays as $attempt => $delay) {
            try {
                if ($delay > 0) {
                    app('log')->debug(sprintf('Retrying account creation for "%s" after %ds delay (attempt %d)', $accountName, $delay, $attempt + 1));
                    sleep($delay);
                }

                return $request->post();

            } catch (ApiHttpException $e) {
                $lastException = $e;
                $errorMessage = $e->getMessage();

                // Check if this is a DNS/connection timeout error that we should retry
                $shouldRetry = $this->shouldRetryApiCall($errorMessage, $attempt, count($retryDelays));

                if (!$shouldRetry) {
                    app('log')->error(sprintf('Non-retryable API error for account "%s": %s', $accountName, $errorMessage));
                    throw $e;
                }

                app('log')->warning(sprintf('DNS/connection error for account "%s" (attempt %d): %s', $accountName, $attempt + 1, $errorMessage));

                // If this was the last attempt, we'll throw after the loop
                if ($attempt === count($retryDelays) - 1) {
                    break;
                }
            }
        }

        // All retries exhausted
        app('log')->error(sprintf('All retries exhausted for account "%s": %s', $accountName, $lastException->getMessage()));
        throw $lastException;
    }

    /**
     * Determine if an API call should be retried based on the error
     * @param string $errorMessage
     * @param int $attempt
     * @param int $maxAttempts
     * @return bool
     */
    private function shouldRetryApiCall(string $errorMessage, int $attempt, int $maxAttempts): bool
    {
        // Don't retry if we've exhausted all attempts
        if ($attempt >= $maxAttempts - 1) {
            return false;
        }

        // Retry on DNS resolution timeouts, connection timeouts, and network errors
        $retryableErrors = [
            'Resolving timed out',
            'cURL error 28',
            'Connection timed out',
            'cURL error 6',  // Couldn't resolve host
            'cURL error 7',  // Couldn't connect to host
            'Failed to connect',
            'Name or service not known',
            'Temporary failure in name resolution'
        ];

        foreach ($retryableErrors as $retryableError) {
            if (stripos($errorMessage, $retryableError) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get mapping options for UI
     * @param SimpleFINAccount $simplefinAccount
     * @return array<string,mixed>
     */
    public function getMappingOptions(SimpleFINAccount $simplefinAccount): array
    {
        $this->loadFireflyAccounts();

        $options = [
            'account_name' => $simplefinAccount->getName(),
            'account_id' => $simplefinAccount->getId(),
            'currency' => $simplefinAccount->getCurrency(),
            'balance' => $simplefinAccount->getBalance(),
            'organization' => $simplefinAccount->getOrganizationName() ?? $simplefinAccount->getOrganizationDomain(),
            'firefly_accounts' => [],
            'suggested_account' => null,
        ];

        // Add all available Firefly accounts as options
        foreach ($this->fireflyAccounts as $account) {
            $options['firefly_accounts'][] = [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'currency_code' => $account->currencyCode ?? 'USD',
            ];
        }

        // Try to suggest a matching account
        $suggested = $this->findMatchingFireflyAccount($simplefinAccount);
        if ($suggested) {
            $options['suggested_account'] = [
                'id' => $suggested->id,
                'name' => $suggested->name,
                'type' => $suggested->type,
            ];
        }

        return $options;
    }

    /**
     * Get created accounts during this session
     */
    public function getCreatedAccounts(): array
    {
        return $this->createdAccounts;
    }
}