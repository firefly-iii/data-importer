<?php

/*
 * ConfigurationContractValidator.php
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

namespace App\Services\SimpleFIN\Validation;

use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Configuration\Configuration;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountsResponse;
use Illuminate\Support\Facades\Log;

/**
 * Class ConfigurationContractValidator
 *
 * Validates the data contract between SimpleFIN configuration and conversion steps
 */
class ConfigurationContractValidator
{
    private array $errors                    = [];
    private array $warnings                  = [];
    private array $existingAccounts          = [];

    private const REQUIRED_ACCOUNT_TYPES     = ['asset', 'liability', 'expense', 'revenue'];
    private const VALID_ACCOUNT_ROLES        = ['defaultAsset', 'sharedAsset', 'savingAsset', 'ccAsset', 'cashWalletAsset'];
    private const VALID_LIABILITY_TYPES      = ['debt', 'loan', 'mortgage'];
    private const VALID_LIABILITY_DIRECTIONS = ['credit', 'debit'];

    public function validateConfigurationContract(Configuration $configuration): ValidationResult
    {
        $this->errors   = [];
        $this->warnings = [];

        Log::debug('Starting SimpleFIN configuration contract validation');

        // Load existing accounts first for duplicate validation
        $this->loadExistingAccounts();

        // Core validation
        $this->validateSimpleFINFlow($configuration);
        $this->validateSessionData($configuration);
        $this->validateAccountMappings($configuration);
        $this->validateNewAccountConfigurations($configuration);
        $this->validateImportSelections($configuration);

        return new ValidationResult(
            0 === count($this->errors),
            $this->errors,
            $this->warnings
        );
    }

    private function validateSimpleFINFlow(Configuration $configuration): void
    {
        if ('simplefin' !== $configuration->getFlow()) {
            $this->addError('configuration.flow', 'Configuration must be SimpleFIN flow', $configuration->getFlow());
        }
    }

    private function validateSessionData(Configuration $configuration): void
    {
        // Check for SimpleFIN accounts data in session
        $sessionData = session()->get('simplefin_accounts_data');
        if (!is_array($sessionData) || 0 === count($sessionData)) {
            $this->addError('session.simplefin_accounts_data', 'SimpleFIN accounts data missing from session');

            return;
        }

        // Validate SimpleFIN account structure
        foreach ($sessionData as $index => $account) {
            $this->validateSimpleFINAccount($account, $index);
        }
    }

    private function validateSimpleFINAccount(array $account, int $index): void
    {
        $requiredFields = ['id', 'name', 'currency', 'balance', 'balance-date', 'org'];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $account)) {
                $this->addError("session.simplefin_accounts_data.{$index}.{$field}", "Required field '{$field}' missing from SimpleFIN account");
            }
        }

        // Validate currency format (should be 3-letter ISO code)
        if (isset($account['currency']) && !preg_match('/^[A-Z]{3}$/', $account['currency'])) {
            $this->addWarning("session.simplefin_accounts_data.{$index}.currency", 'Currency should be 3-letter ISO code', $account['currency']);
        }

        // Validate balance is numeric
        if (isset($account['balance']) && !is_numeric($account['balance'])) {
            $this->addError("session.simplefin_accounts_data.{$index}.balance", 'Balance must be numeric', $account['balance']);
        }

        // Validate balance-date is unix timestamp
        if (isset($account['balance-date']) && (!is_numeric($account['balance-date']) || $account['balance-date'] < 0)) {
            $this->addError("session.simplefin_accounts_data.{$index}.balance-date", 'Balance date must be valid unix timestamp', $account['balance-date']);
        }
    }

    private function validateAccountMappings(Configuration $configuration): void
    {
        $accounts = $configuration->getAccounts();
        if (0 === count($accounts)) {
            $this->addError('configuration.accounts', 'Account mappings cannot be empty');

            return;
        }

        foreach ($accounts as $simplefinId => $fireflyId) {
            // Validate SimpleFIN ID format
            if (!is_string($simplefinId) || '' === (string)$simplefinId) {
                $this->addError('configuration.accounts.key', 'SimpleFIN account ID must be non-empty string', $simplefinId);
            }

            // Validate Firefly III ID (0 means create new, positive integer means existing account)
            if (!is_int($fireflyId) || $fireflyId < 0) {
                $this->addError("configuration.accounts.{$simplefinId}", 'Firefly III account ID must be non-negative integer', $fireflyId);
            }
        }
    }

    private function validateNewAccountConfigurations(Configuration $configuration): void
    {
        $newAccounts = $configuration->getNewAccounts();
        $accounts    = $configuration->getAccounts();

        foreach ($accounts as $simplefinId => $fireflyId) {
            if (0 === $fireflyId) {
                // This account should be created, validate its configuration
                if (!isset($newAccounts[$simplefinId])) {
                    $this->addError("configuration.new_account.{$simplefinId}", 'New account configuration missing for account marked for creation');

                    continue;
                }

                $this->validateNewAccountConfig($newAccounts[$simplefinId], $simplefinId);
            }
        }

        // Check for orphaned new account configurations
        foreach ($newAccounts as $simplefinId => $config) {
            if (!isset($accounts[$simplefinId]) || 0 !== $accounts[$simplefinId]) {
                $this->addWarning("configuration.new_account.{$simplefinId}", 'New account configuration exists but account not marked for creation');
            }
        }
    }

    private function validateNewAccountConfig(array $config, string $simplefinId): void
    {
        $requiredFields = ['name', 'type', 'currency', 'opening_balance'];

        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || (is_string($config[$field]) && '' === trim($config[$field]))) {
                $this->addError("configuration.new_account.{$simplefinId}.{$field}", "Required field '{$field}' missing or empty");
            }
        }

        // Validate account type
        if (isset($config['type']) && !in_array($config['type'], self::REQUIRED_ACCOUNT_TYPES, true)) {
            $this->addError("configuration.new_account.{$simplefinId}.type", 'Invalid account type', $config['type']);
        }

        // Validate account role for asset accounts
        if (isset($config['type']) && 'asset' === $config['type'] && isset($config['account_role'])) {
            if (!in_array($config['account_role'], self::VALID_ACCOUNT_ROLES, true)) {
                $this->addError("configuration.new_account.{$simplefinId}.account_role", 'Invalid account role for asset account', $config['account_role']);
            }
        }

        // Validate liability-specific fields
        if (isset($config['type']) && 'liability' === $config['type']) {
            if (!isset($config['liability_type']) || !in_array($config['liability_type'], self::VALID_LIABILITY_TYPES, true)) {
                $this->addError("configuration.new_account.{$simplefinId}.liability_type", 'Liability type required and must be valid', $config['liability_type'] ?? null);
            }

            if (!isset($config['liability_direction']) || !in_array($config['liability_direction'], self::VALID_LIABILITY_DIRECTIONS, true)) {
                $this->addError("configuration.new_account.{$simplefinId}.liability_direction", 'Liability direction required and must be valid', $config['liability_direction'] ?? null);
            }
        }

        // Validate currency format
        if (isset($config['currency']) && !preg_match('/^[A-Z]{3}$/', $config['currency'])) {
            $this->addError("configuration.new_account.{$simplefinId}.currency", 'Currency must be 3-letter ISO code', $config['currency']);
        }

        // Validate opening balance is numeric
        if (isset($config['opening_balance']) && !is_numeric($config['opening_balance'])) {
            $this->addError("configuration.new_account.{$simplefinId}.opening_balance", 'Opening balance must be numeric', $config['opening_balance']);
        }

        // Validate opening balance date format if provided
        if (isset($config['opening_balance_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $config['opening_balance_date'])) {
            $this->addError("configuration.new_account.{$simplefinId}.opening_balance_date", 'Opening balance date must be YYYY-MM-DD format', $config['opening_balance_date']);
        }

        // Validate account name length and content
        if (isset($config['name'])) {
            if (strlen($config['name']) > 255) {
                $this->addError("configuration.new_account.{$simplefinId}.name", 'Account name too long (max 255 characters)');
            }
            if ('' === trim($config['name'])) {
                $this->addError("configuration.new_account.{$simplefinId}.name", 'Account name cannot be empty');
            }
        }

        // Validate no duplicate account name/type combinations
        $this->validateNoDuplicateAccount($config, $simplefinId);
    }

    private function validateImportSelections(Configuration $configuration): void
    {
        $doImport = session()->get('do_import', []);
        if (0 === count($doImport)) {
            $this->addError('session.do_import', 'No accounts selected for import');

            return;
        }

        $accounts = $configuration->getAccounts();
        foreach ($doImport as $accountId => $selected) {
            if ('1' !== $selected) {
                continue; // Not selected for import
            }

            if (!isset($accounts[$accountId])) {
                $this->addError('session.do_import', 'Account selected for import but not in account mappings', $accountId);
            }
        }

        // Validate that all mapped accounts are either selected for import or explicitly excluded
        foreach ($accounts as $simplefinId => $fireflyId) {
            if (!isset($doImport[$simplefinId])) {
                $this->addWarning('session.do_import', 'Account mapped but no import selection specified', $simplefinId);
            }
        }
    }

    private function addError(string $field, string $message, $value = null): void
    {
        $this->errors[] = [
            'field'   => $field,
            'message' => $message,
            'value'   => $value,
        ];

        Log::error("Configuration contract validation error: {$field} - {$message}", [
            'value' => $value,
        ]);
    }

    private function addWarning(string $field, string $message, $value = null): void
    {
        $this->warnings[] = [
            'field'   => $field,
            'message' => $message,
            'value'   => $value,
        ];

        Log::warning("Configuration contract validation warning: {$field} - {$message}", [
            'value' => $value,
        ]);
    }

    private function loadExistingAccounts(): void
    {
        try {
            $url      = SecretManager::getBaseUrl();
            $token    = SecretManager::getAccessToken();
            $request  = new GetAccountsRequest($url, $token);
            $request->setType(GetAccountsRequest::ALL);
            $request->setVerify(config('importer.connection.verify'));
            $request->setTimeOut(config('importer.connection.timeout'));

            $response = $request->get();
            if ($response instanceof GetAccountsResponse) {
                $this->existingAccounts = iterator_to_array($response);
                Log::debug(sprintf('Loaded %d existing Firefly III accounts for duplicate validation', count($this->existingAccounts)));
            }
        } catch (ApiHttpException $e) {
            Log::warning(sprintf('Could not load existing accounts for duplicate validation: %s', $e->getMessage()));
            // Don't fail validation entirely, just log the warning
            $this->existingAccounts = [];
        }
    }

    private function validateNoDuplicateAccount(array $config, string $simplefinId): void
    {
        if (!isset($config['name']) || !isset($config['type'])) {
            return; // Cannot validate without name and type
        }

        $accountName = trim($config['name']);
        $accountType = $config['type'];

        // Check against existing accounts
        foreach ($this->existingAccounts as $existingAccount) {
            if (!$existingAccount instanceof Account) {
                continue;
            }

            // Check for exact name match (case-insensitive) and type match
            if (strtolower($existingAccount->name) === strtolower($accountName)
                && $existingAccount->type === $accountType) {
                $this->addError(
                    "configuration.new_account.{$simplefinId}.name",
                    sprintf('Account "%s" of type "%s" already exists. Cannot create duplicate account.', $accountName, $accountType),
                    $accountName
                );

                return;
            }
        }

        Log::debug(sprintf('No duplicate found for account "%s" of type "%s"', $accountName, $accountType));
    }

    /**
     * Check if a single account name and type combination already exists
     * Used for AJAX duplicate checking during account creation
     */
    public function checkSingleAccountDuplicate(string $accountName, string $accountType): bool
    {
        $accountName = trim($accountName);
        $accountType = trim($accountType);

        // Empty name or type cannot be duplicate
        if ('' === $accountName || '' === $accountType) {
            Log::debug('DUPLICATE_CHECK: Empty name or type provided');

            return false;
        }

        // Load existing accounts if not already loaded
        if (0 === count($this->existingAccounts)) {
            Log::debug('DUPLICATE_CHECK: Loading existing accounts for validation');
            $this->loadExistingAccounts();
        }

        // If loading failed, return false to avoid blocking user (graceful degradation)
        if (0 === count($this->existingAccounts)) {
            Log::warning('DUPLICATE_CHECK: No existing accounts loaded, cannot validate duplicates');

            return false;
        }

        // Check against existing accounts
        foreach ($this->existingAccounts as $existingAccount) {
            if (!$existingAccount instanceof Account) {
                continue;
            }

            // Check for exact name match (case-insensitive) and type match
            if (strtolower($existingAccount->name) === strtolower($accountName)
                && $existingAccount->type === $accountType) {
                Log::debug('DUPLICATE_CHECK: Found duplicate account', [
                    'requested_name' => $accountName,
                    'requested_type' => $accountType,
                    'existing_name'  => $existingAccount->name,
                    'existing_type'  => $existingAccount->type,
                ]);

                return true;
            }
        }

        Log::debug('DUPLICATE_CHECK: No duplicate found', [
            'requested_name'   => $accountName,
            'requested_type'   => $accountType,
            'checked_accounts' => count($this->existingAccounts),
        ]);

        return false;
    }

    public function validateFormFieldStructure(array $formData): ValidationResult
    {
        $this->errors      = [];
        $this->warnings    = [];

        Log::debug('Validating SimpleFIN form field structure');

        // Validate expected form structure
        $expectedStructure = [
            'do_import'   => 'array',
            'accounts'    => 'array',
            'new_account' => 'array',
        ];

        foreach ($expectedStructure as $field => $expectedType) {
            if (!isset($formData[$field])) {
                $this->addError("form.{$field}", "Required form field '{$field}' missing");

                continue;
            }

            if ('array' === $expectedType && !is_array($formData[$field])) {
                $this->addError("form.{$field}", "Form field '{$field}' must be array");
            }
        }

        // Validate new_account structure follows expected pattern
        if (isset($formData['new_account']) && is_array($formData['new_account'])) {
            foreach ($formData['new_account'] as $accountId => $accountData) {
                if (!is_array($accountData)) {
                    $this->addError("form.new_account.{$accountId}", 'Account data must be array');

                    continue;
                }

                $this->validateFormAccountData($accountData, $accountId);
            }
        }

        return new ValidationResult(
            0 === count($this->errors),
            $this->errors,
            $this->warnings
        );
    }

    private function validateFormAccountData(array $accountData, string $accountId): void
    {
        $expectedFields = ['name', 'type', 'currency', 'opening_balance'];

        foreach ($expectedFields as $field) {
            if (!isset($accountData[$field])) {
                $this->addError("form.new_account.{$accountId}.{$field}", "Required form field '{$field}' missing");
            }
        }

        // Check for properly structured field names
        if (isset($accountData['account_role']) && 'asset' !== ($accountData['type'] ?? '')) {
            $this->addWarning("form.new_account.{$accountId}.account_role", 'Account role specified for non-asset account');
        }
    }
}
