<?php
/*
 * AccountMapper.php
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

namespace App\Services\Shared\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Services\CSV\Converter\Iban as IbanConverter;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Model\ImportServiceAccount;
use App\Services\SimpleFIN\Model\Account as SimpleFINAccount;
use App\Services\SimpleFIN\Request\PostAccountRequest;
use App\Services\SimpleFIN\Response\PostAccountResponse;
use Carbon\Carbon;
use Exception;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Model\AccountType;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use GrumpyDictator\FFIIIApiSupport\Request\GetSearchAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountsResponse;
use GrumpyDictator\FFIIIApiSupport\Response\Response;
use GrumpyDictator\FFIIIApiSupport\Response\ValidationErrorResponse;
use Illuminate\Support\Facades\Log;

class AccountMapper
{
    private array $fireflyIIIAccounts = [];
    private array $accountMapping     = [];
    private array $createdAccounts    = [];

    public function __construct()
    {
        // Defer account loading until actually needed to avoid authentication errors
        // during constructor when authentication context may not be available
    }

    /**
     * Map SimpleFIN accounts to Firefly III accounts
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

                if ('map' === $mappingConfig['action'] && isset($mappingConfig['firefly_account_id'])) {
                    // Map to existing account
                    $fireflyAccount = $this->getFireflyAccountById((int)$mappingConfig['firefly_account_id']);
                    if ($fireflyAccount instanceof Account) {
                        $mapping[$accountKey] = [
                            'simplefin_account'    => $simplefinAccount,
                            'firefly_account_id'   => $fireflyAccount->id,
                            'firefly_account_name' => $fireflyAccount->name,
                            'action'               => 'map',
                        ];
                    }
                }
                if ('create' === $mappingConfig['action']) {
                    // Create new account
                    $fireflyAccount = $this->createFireflyIIIAccount($simplefinAccount, $mappingConfig);
                    if ($fireflyAccount instanceof Account) {
                        $mapping[$accountKey] = [
                            'simplefin_account'    => $simplefinAccount,
                            'firefly_account_id'   => $fireflyAccount->id,
                            'firefly_account_name' => $fireflyAccount->name,
                            'action'               => 'create',
                        ];
                    }
                }
            }
            if (!isset($configuration['account_mapping'][$accountKey])) {
                // Auto-map by searching for existing accounts
                $converted = ImportServiceAccount::convertSingleAccount($simplefinAccount);
                $fireflyAccount = $this->findMatchingFireflyIIIAccount($converted);
                if ($fireflyAccount instanceof Account) {
                    $mapping[$accountKey] = [
                        'simplefin_account'    => $simplefinAccount,
                        'firefly_account_id'   => $fireflyAccount->id,
                        'firefly_account_name' => $fireflyAccount->name,
                        'action'               => 'auto_map',
                    ];
                }
                if (!$fireflyAccount instanceof Account) {
                    // No mapping found - will need user input
                    $mapping[$accountKey] = [
                        'simplefin_account'    => $simplefinAccount,
                        'firefly_account_id'   => null,
                        'firefly_account_name' => null,
                        'action'               => 'unmapped',
                    ];
                }
            }
        }

        return $mapping;
    }

    /**
     * Find a matching Firefly III account for a SimpleFIN account
     */
    public function findMatchingFireflyIIIAccount(ImportServiceAccount $account): ?Account
    {
        $this->loadFireflyIIIAccounts();

        // Try to find by name first
        $matchingAccounts = array_filter($this->fireflyIIIAccounts, fn(Account $current) => strtolower((string)$current->name) === strtolower($account->name));

        if (0 === count($matchingAccounts)) {
            return null;
        }

        Log::debug(sprintf('Search for Firefly III account with name "%s"', $account->name));
        // Try to search via API
        try {
            $request = new GetSearchAccountRequest(SecretManager::getBaseUrl(), SecretManager::getAccessToken());
            $request->setField('name');
            $request->setQuery($account->name);
            $response = $request->get();

            if ($response instanceof GetAccountsResponse && count($response) > 0) {
                foreach ($response as $current) {
                    if (strtolower($current->name) === strtolower($account->name)) {
                        return $current;
                    }
                }
            }
        } catch (ApiHttpException $e) {
            Log::warning(sprintf('Could not search for account "%s": %s', $account->name, $e->getMessage()));
        }

        return null;
    }

    /**
     * Create account immediately via Firefly III API
     */
    public function createFireflyIIIAccount(ImportServiceAccount $importServiceAccount, array $config): ?Account
    {
        $accountName    = $config['name'] ?? $importServiceAccount->name;
        $accountType    = $this->determineAccountType($config);
        $currencyCode   = $this->getCurrencyCode($importServiceAccount, $config);
        $openingBalance = $config['opening_balance'] ?? '0.00';

        Log::info(sprintf('Creating Firefly III account "%s" via API', $accountName));

        try {
            $request = new PostAccountRequest(SecretManager::getBaseUrl(), SecretManager::getAccessToken());

            // Build account creation payload
            $payload = [
                'name'              => $accountName,
                'type'              => $accountType,
                'currency_code'     => $currencyCode,
                'opening_balance'   => $openingBalance,
                'active'            => true,
                'include_net_worth' => true,
            ];

            // Add opening balance date if opening balance is provided
            if ('' !== (string)$config['opening_balance'] && is_numeric($config['opening_balance'])) {
                $payload['opening_balance_date'] = $config['opening_balance_date'] ?? Carbon::now()->format('Y-m-d');
            }

            // Add account role for asset accounts
            if (AccountType::ASSET === $accountType) {
                $payload['account_role'] = $config['account_role'] ?? 'defaultAsset';
            }

            // Add liability-specific fields for liability accounts
            if (in_array($accountType, [AccountType::DEBT, AccountType::LOAN, AccountType::MORTGAGE, AccountType::LIABILITIES, 'liability'], true)) {
                // Map account type to liability type
                $liabilityTypeMap = [
                    AccountType::DEBT        => 'debt',
                    AccountType::LOAN        => 'loan',
                    AccountType::MORTGAGE    => 'mortgage',
                    AccountType::LIABILITIES => 'debt', // Default generic liabilities to debt
                    'liability'              => 'debt', // Handle user-provided 'liability' type
                ];

                $payload['liability_type']      = $config['liability_type'] ?? $liabilityTypeMap[$accountType] ?? 'debt';
                $payload['liability_direction'] = $config['liability_direction'] ?? 'credit';
            }

            // Add IBAN if provided
            if (array_key_exists('iban', $config) && '' !== (string)$config['iban'] && IbanConverter::isValidIban((string)$config['iban'])) {
                $payload['iban'] = $config['iban'];
            }

            // Add account number if provided
            if (array_key_exists('account_number', $config) && '' !== (string)$config['account_number']) {
                $payload['account_number'] = $config['account_number'];
            }

            $request->setBody($payload);
            $response = $this->makeApiCallWithRetry($request, $accountName);

            if ($response instanceof ValidationErrorResponse) {
                Log::error(sprintf('Failed to create account "%s": %s', $accountName, json_encode($response->errors->toArray())));

                return null;
            }

            if ($response instanceof PostAccountResponse) {
                $account = $response->getAccount();
                if ($account instanceof Account) {
                    Log::info(sprintf('Successfully created account "%s" with ID %d', $accountName, $account->id));

                    // Add to our local cache
                    $this->fireflyIIIAccounts[] = $account;
                    $this->createdAccounts[]    = $account;

                    return $account;
                }
            }

            Log::error(sprintf('Unexpected response type when creating account "%s"', $accountName));

            return null;

        } catch (ApiHttpException $e) {
            Log::error(sprintf('API error creating account "%s": %s', $accountName, $e->getMessage()));

            return null;
        } catch (Exception $e) {
            Log::error(sprintf('Unexpected error creating account "%s": %s', $accountName, $e->getMessage()));

            return null;
        }
    }

    /**
     * Determine the appropriate Firefly III account type
     */
    private function determineAccountType(array $config): string
    {
        // Default to asset account for most SimpleFIN accounts
        return $config['type'] ?? AccountType::ASSET;
    }

    /**
     * Get currency code for account creation
     */
    private function getCurrencyCode(ImportServiceAccount $account, array $config): string
    {
        // 1. Use user-configured currency first
        if (array_key_exists('currency', $config) && '' !== (string)$config['currency']) {
            return (string)$config['currency'];
        }

        // 2. Fall back to account currency
        $currency = $account->currencyCode;

        // 3. Final fallback
        return '' !== $currency && '0' !== $currency ? $currency : 'EUR';
    }

    /**
     * Get Firefly III account by ID
     */
    private function getFireflyAccountById(int $id): ?Account
    {
        $this->loadFireflyIIIAccounts();

        return array_find($this->fireflyIIIAccounts, fn($account) => $account->id === $id);

    }

    /**
     * Load all Firefly III accounts
     */
    private function loadFireflyIIIAccounts(): void
    {
        // Only load once
        if (count($this->fireflyIIIAccounts) > 0) {
            Log::debug('Already loaded Firefly III accounts, skipping reload');
            return;
        }

        try {
            // Verify authentication context before making API calls
            $baseUrl     = SecretManager::getBaseUrl();
            $accessToken = SecretManager::getAccessToken();

            if ('' === $baseUrl || '' === $accessToken) {
                Log::warning('Missing authentication context for Firefly III account loading');

                throw new ImporterErrorException('Authentication context not available for account loading');
            }

            $request = new GetAccountsRequest($baseUrl, $accessToken);
            $request->setType(AccountType::ASSET);
            $response = $request->get();

            if ($response instanceof GetAccountsResponse) {
                $this->fireflyIIIAccounts = iterator_to_array($response);
                Log::debug(sprintf('Loaded %d Firefly III accounts', count($this->fireflyIIIAccounts)));
            }
        } catch (ApiHttpException $e) {
            Log::error(sprintf('Could not load Firefly III accounts: %s', $e->getMessage()));

            throw new ImporterErrorException(sprintf('Could not load Firefly III accounts: %s', $e->getMessage()));
        }
    }

    /**
     * Make API call with DNS resilience retry pattern
     *
     * @throws ApiHttpException
     */
    private function makeApiCallWithRetry(PostAccountRequest $request, string $accountName): Response
    {
        $retryDelays   = [0, 2, 5]; // immediate, 2s delay, 5s delay
        $lastException = null;

        foreach ($retryDelays as $attempt => $delay) {
            try {
                if ($delay > 0) {
                    Log::debug(sprintf('Retrying account creation for "%s" after %ds delay (attempt %d)', $accountName, $delay, $attempt + 1));
                    sleep($delay);
                }

                return $request->post();

            } catch (ApiHttpException $e) {
                $lastException = $e;
                $errorMessage  = $e->getMessage();

                // Check if this is a DNS/connection timeout error that we should retry
                $shouldRetry = $this->shouldRetryApiCall($errorMessage, $attempt, count($retryDelays));

                if (!$shouldRetry) {
                    Log::error(sprintf('Non-retryable API error for account "%s": %s', $accountName, $errorMessage));

                    throw $e;
                }

                Log::warning(sprintf('DNS/connection error for account "%s" (attempt %d): %s', $accountName, $attempt + 1, $errorMessage));

                // If this was the last attempt, we'll throw after the loop
                if ($attempt === count($retryDelays) - 1) {
                    break;
                }
            }
        }

        // All retries exhausted
        Log::error(sprintf('All retries exhausted for account "%s": %s', $accountName, $lastException->getMessage()));

        throw $lastException;
    }

    /**
     * Determine if an API call should be retried based on the error
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
            'Temporary failure in name resolution',
        ];

        return array_any($retryableErrors, fn($retryableError) => false !== stripos($errorMessage, $retryableError));

    }

    /**
     * Get mapping options for UI
     *
     * @return array<string,mixed>
     */
    public function getMappingOptions(SimpleFINAccount $simplefinAccount): array
    {
        $this->loadFireflyAccounts();

        $options = [
            'account_name'      => $simplefinAccount->getName(),
            'account_id'        => $simplefinAccount->getId(),
            'currency'          => $simplefinAccount->getCurrency(),
            'balance'           => $simplefinAccount->getBalance(),
            'organization'      => $simplefinAccount->getOrganizationName() ?? $simplefinAccount->getOrganizationDomain(),
            'firefly_accounts'  => [],
            'suggested_account' => null,
        ];

        // Add all available Firefly accounts as options
        foreach ($this->fireflyIIIAccounts as $account) {
            $options['firefly_accounts'][] = [
                'id'            => $account->id,
                'name'          => $account->name,
                'type'          => $account->type,
                'currency_code' => $account->currencyCode ?? 'EUR',
            ];
        }

        // Try to suggest a matching account
        $converted = ImportServiceAccount::convertSingleAccount($simplefinAccount);
        $suggested = $this->findMatchingFireflyIIIAccount($converted);
        if ($suggested instanceof Account) {
            $options['suggested_account'] = [
                'id'   => $suggested->id,
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
