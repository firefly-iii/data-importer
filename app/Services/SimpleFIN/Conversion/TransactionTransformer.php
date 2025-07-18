<?php

/*
 * TransactionTransformer.php
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

use App\Services\CSV\Converter\Amount;
use App\Support\Http\CollectsAccounts;
use Carbon\Carbon;
use App\Services\Shared\Authentication\SecretManager;
use Illuminate\Support\Facades\Log;
use Exception;

// Removed SimpleFINModel imports as we now use arrays

/**
 * Class TransactionTransformer
 */
class TransactionTransformer
{
    use CollectsAccounts;

    private array $expenseAccounts            = [];
    private array $revenueAccounts            = [];
    private bool $accountsCollected           = false;
    private array $pendingTransactionClusters = []; // For clustering similar transactions in clean instances

    public function __construct()
    {
        bcscale(12);
    }

    /**
     * Transform SimpleFIN transaction data (array) to Firefly III transaction format
     *
     * @param array $transactionData      Raw transaction data from SimpleFIN JSON
     * @param array $simpleFINAccountData Raw account data from SimpleFIN JSON for the account this transaction belongs to
     * @param array $accountMapping       Mapping configuration for Firefly III accounts
     * @param array $newAccountConfig     User-provided new account configuration data
     */
    public function transform(array $transactionData, array $simpleFINAccountData, array $accountMapping = [], array $newAccountConfig = []): array
    {
        // Ensure amount is a float. SimpleFIN provides it as a string.
        $amount                = $transactionData['amount'] ?? '0.0';

        // Skip zero-amount transactions as they're invalid for Firefly III
        if (0 === bccomp('0', $amount)) {
            Log::warning('Skipping zero-amount transaction', [
                'transaction_id' => $transactionData['id'] ?? 'unknown',
                'description'    => $transactionData['description'] ?? 'unknown',
            ]);

            return [];
        }

        $isDeposit             = -1 === bccomp('0', $amount);
        $absoluteAmount        = Amount::positive($amount);

        // Determine transaction type and accounts
        if ($isDeposit) {
            $type               = 'deposit';
            $sourceAccount      = $this->getCounterAccount($transactionData, true);
            $destinationAccount = $this->getFireflyAccount($simpleFINAccountData, $accountMapping, $newAccountConfig);
        }
        if (!$isDeposit) {
            $type               = 'withdrawal';
            $sourceAccount      = $this->getFireflyAccount($simpleFINAccountData, $accountMapping, $newAccountConfig);
            $destinationAccount = $this->getCounterAccount($transactionData, false);
        }

        // Use 'posted' date as the primary transaction date.
        // SimpleFIN 'posted' is a UNIX timestamp.
        $transactionTimestamp  = isset($transactionData['posted']) ? (int)$transactionData['posted'] : Carbon::now()->timestamp;
        $transactionDateCarbon = Carbon::createFromTimestamp($transactionTimestamp);

        return [
            'type'                  => $type,
            'date'                  => $transactionDateCarbon->format('Y-m-d'),
            'amount'                => $absoluteAmount,
            'description'           => $this->sanitizeDescription($transactionData['description'] ?? 'N/A'),
            'source_id'             => $sourceAccount['id'] ?? null,
            'source_name'           => $sourceAccount['name'] ?? null,
            'source_iban'           => $sourceAccount['iban'] ?? null,
            'source_number'         => $sourceAccount['number'] ?? null,
            'source_bic'            => $sourceAccount['bic'] ?? null,
            'destination_id'        => $destinationAccount['id'] ?? null,
            'destination_name'      => $destinationAccount['name'] ?? null,
            'destination_iban'      => $destinationAccount['iban'] ?? null,
            'destination_number'    => $destinationAccount['number'] ?? null,
            'destination_bic'       => $destinationAccount['bic'] ?? null,
            'currency_code'         => $this->getCurrencyCode($simpleFINAccountData),
            'category_name'         => $this->extractCategory($transactionData),
            'reconciled'            => false,
            'notes'                 => $this->buildNotes($transactionData),
            'tags'                  => $this->extractTags($transactionData),
            'internal_reference'    => $transactionData['id'] ?? null,
            'external_id'           => $this->buildExternalId($transactionData, $simpleFINAccountData),
            'book_date'             => $this->getBookDate($transactionData),
            'process_date'          => $this->getProcessDate($transactionData),
        ];
    }

    /**
     * Get the Firefly III account information from mapping or account data
     */
    private function getFireflyAccount(array $simpleFINAccountData, array $accountMapping, array $newAccountConfig = []): array
    {
        $accountKey       = $simpleFINAccountData['id'] ?? null;

        // Check for user-provided account name first, then fall back to SimpleFIN account name
        $userProvidedName = null;
        if ($accountKey && isset($newAccountConfig[$accountKey]['name'])) {
            $userProvidedName = $newAccountConfig[$accountKey]['name'];
        }

        $accountName      = $userProvidedName ?? $simpleFINAccountData['name'] ?? 'Unknown SimpleFIN Account';

        // Check if account is mapped and has a valid (non-zero) Firefly III account ID
        if ($accountKey && isset($accountMapping[$accountKey]) && $accountMapping[$accountKey] > 0) {
            return [
                'id'     => $accountMapping[$accountKey], // Configuration maps SimpleFIN account ID directly to Firefly account ID
                'name'   => $accountName,
                'iban'   => null,
                'number' => $accountKey,
                'bic'    => null,
            ];
        }

        // No mapping or mapped to 0 (deferred creation) - return null ID to trigger name-based account creation
        return [
            'id'     => null,
            'name'   => $accountName,
            'iban'   => null,
            'number' => $accountKey,
            'bic'    => null,
        ];
    }

    /**
     * Get counter account (revenue/expense account) based on transaction data
     */
    private function getCounterAccount(array $transactionData, bool $isDeposit): array
    {
        $description        = $transactionData['description'] ?? 'N/A';

        // Ensure accounts are collected
        $this->ensureAccountsCollected();

        // Try to find existing expense or revenue account first
        $existingAccount    = $this->findExistingAccount($description, $isDeposit);
        if (null !== $existingAccount && [] !== $existingAccount) {
            return [
                'id'     => $existingAccount['id'],
                'name'   => $existingAccount['name'],
                'iban'   => null,
                'number' => null,
                'bic'    => null,
            ];
        }

        // For clean instances: try clustering when no existing accounts found
        // This includes both clean instances (successful collection of zero accounts)
        // and failed collection scenarios
        if (config('simplefin.enable_transaction_clustering', true)) {
            $accountsToCheck = $isDeposit ? $this->revenueAccounts : $this->expenseAccounts;

            if (0 === count($accountsToCheck)) {
                $clusteredAccountName = $this->findClusteredAccountName($description, $isDeposit);
                if (null !== $clusteredAccountName && '' !== $clusteredAccountName && '0' !== $clusteredAccountName) {
                    return [
                        'id'     => null,
                        'name'   => $clusteredAccountName,
                        'iban'   => null,
                        'number' => null,
                        'bic'    => null,
                    ];
                }
            }
        }
        // Fallback: extract meaningful counter account name from description
        $counterAccountName = $this->extractCounterAccountName($description);

        // Check if automatic account creation is enabled
        if (!config('simplefin.auto_create_expense_accounts', true)) {
            Log::warning(sprintf(
                'Auto-creation disabled. No %s account will be created for "%s"',
                $isDeposit ? 'revenue' : 'expense',
                $description
            ));

            return [
                'id'     => null,
                'name'   => $counterAccountName,
                'iban'   => null,
                'number' => null,
                'bic'    => null,
            ];
        }



        Log::info(sprintf('Creating new %s account "%s" for transaction "%s"', $isDeposit ? 'revenue' : 'expense', $counterAccountName, $description));

        return [
            'id'     => null,
            'name'   => $counterAccountName,
            'iban'   => null,
            'number' => null,
            'bic'    => null,
        ];
    }

    /**
     * Extract a meaningful counter account name from transaction description
     */
    private function extractCounterAccountName(string $description): string
    {
        // Clean up and format the description for use as account name
        $cleaned  = trim($description);

        // Remove common prefixes/suffixes that don't help identify the account
        $patterns = [
            '/^(PAYMENT|DEPOSIT|TRANSFER|DEBIT|CREDIT)\s+/i',
            '/\s+(PAYMENT|DEPOSIT|TRANSFER|DEBIT|CREDIT)$/i',
            '/^(FROM|TO)\s+/i',
            '/\s+\d{4}[-\/]\d{2}[-\/]\d{2}.*$/', // Remove trailing dates
        ];

        foreach ($patterns as $pattern) {
            $cleaned = preg_replace($pattern, '', (string) $cleaned);
        }

        $cleaned  = trim((string) $cleaned);

        // If we end up with an empty string, use a generic name
        if ('' === $cleaned) {
            $cleaned = 'Unknown';
        }

        // Limit length to reasonable size
        if (strlen($cleaned) > 100) {
            return substr($cleaned, 0, 97).'...';
        }

        return $cleaned;
    }

    /**
     * Get currency code, handling custom currencies
     */
    private function getCurrencyCode(array $simpleFINAccountData): string
    {
        $currency = $simpleFINAccountData['currency'] ?? 'EUR'; // Default to EUR if not present

        // Replicate basic logic from SimpleFINAccount::isCustomCurrency() if it checked for 'XXX' or non-standard codes.
        // For now, pass through, or use a simple check. Let Firefly III handle currency validation.
        // If currency code is not 3 uppercase letters, SimpleFIN spec might imply it's "custom".
        // The previous code returned 'XXX' for custom.
        if (3 === strlen($currency) && ctype_upper($currency)) {
            return $currency;
        }

        return 'XXX'; // Default for non-standard or missing currency codes, matching previous behavior.
    }

    /**
     * Extract category from transaction extra data
     */
    private function extractCategory(array $transactionData): ?string
    {
        $extra          = $transactionData['extra'] ?? null;
        if (!is_array($extra)) {
            return null;
        }

        // Check common category field names
        $categoryFields = ['category', 'Category', 'CATEGORY', 'merchant_category', 'transaction_category'];

        foreach ($categoryFields as $field) {
            if (isset($extra[$field]) && '' !== (string) $extra[$field]) {
                return (string) $extra[$field];
            }
        }

        return null;
    }

    /**
     * Extract tags from transaction extra data
     */
    private function extractTags(array $transactionData): array
    {
        $tags  = [];

        if (isset($transactionData['pending']) && true === $transactionData['pending']) {
            $tags[] = 'pending';
        }

        $extra = $transactionData['extra'] ?? null;
        if (!is_array($extra)) {
            // If no extra data, or not an array, return current tags (e.g. only 'pending' if applicable)
            return array_unique($tags);
        }

        // Look for tags in extra data
        if (isset($extra['tags']) && is_array($extra['tags'])) {
            $tags = array_merge($tags, $extra['tags']);
        }

        // Add organization domain as tag if available
        // Note: We don't have account info here, so this would need to be passed in

        return array_unique($tags);
    }

    /**
     * Build notes from transaction extra data
     */
    private function buildNotes(array $transactionData): ?string
    {
        $notes = [];
        $extra = $transactionData['extra'] ?? null;

        if (isset($transactionData['pending']) && true === $transactionData['pending']) {
            $notes[] = 'Transaction is pending';
        }

        if (is_array($extra)) {
            // Add any extra fields that might be useful as notes
            $noteFields = ['memo', 'notes', 'reference', 'check_number'];

            foreach ($noteFields as $field) {
                if (isset($extra[$field]) && '' !==  (string) $extra[$field]) {
                    $notes[] = sprintf('- %s: %s', ucfirst($field), $extra[$field]);
                }
            }
        }

        return 0 === count($notes) ? null : implode("\n", $notes);
    }

    /**
     * Build external ID for transaction
     */
    private function buildExternalId(array $transactionData, array $simpleFINAccountData): string
    {
        return sprintf('ff3-%s-%s', $simpleFINAccountData['id'] ?? 'unknown_account', $transactionData['id'] ?? 'unknown_transaction');
    }

    /**
     * Get book date from transaction data (using 'posted' timestamp)
     */
    private function getBookDate(array $transactionData): ?string
    {
        if (isset($transactionData['posted']) && (int)$transactionData['posted'] > 0) {
            return Carbon::createFromTimestamp((int)$transactionData['posted'])->format('Y-m-d');
        }

        return null;
    }

    /**
     * Get process date from transaction data
     * SimpleFIN JSON does not typically include a separate 'transacted_at'.
     * This method will return null unless 'transacted_at' is explicitly in $transactionData.
     */
    private function getProcessDate(array $transactionData): ?string
    {
        if (isset($transactionData['transacted_at']) && (int)$transactionData['transacted_at'] > 0) {
            return Carbon::createFromTimestamp((int)$transactionData['transacted_at'])->format('Y-m-d');
        }

        return null;
    }

    /**
     * Ensure expense and revenue accounts are collected from Firefly III
     */
    private function ensureAccountsCollected(): void
    {
        if ($this->accountsCollected) {
            return;
        }

        // Check if smart matching is enabled before attempting collection
        if (!config('simplefin.smart_expense_matching', true)) {
            Log::debug('Smart expense matching is disabled, skipping account collection');
            $this->expenseAccounts   = [];
            $this->revenueAccounts   = [];
            $this->accountsCollected = true;

            return;
        }

        try {
            // Verify authentication context exists before making API calls
            $baseUrl                 = SecretManager::getBaseUrl();
            $accessToken             = SecretManager::getAccessToken();

            if ('' === $baseUrl || '' === $accessToken) {
                Log::warning('Missing authentication context for account collection, skipping smart matching');
                $this->expenseAccounts   = [];
                $this->revenueAccounts   = [];
                $this->accountsCollected = true;

                return;
            }

            Log::debug('Collecting expense accounts from Firefly III');
            $this->expenseAccounts   = $this->collectExpenseAccounts();

            Log::debug('Collecting revenue accounts from Firefly III');
            $this->revenueAccounts   = $this->collectRevenueAccounts();

            Log::debug(sprintf(
                'Collected %d expense accounts and %d revenue accounts',
                count($this->expenseAccounts),
                count($this->revenueAccounts)
            ));

            $this->accountsCollected = true;
        } catch (Exception $e) {
            Log::error(sprintf('Failed to collect accounts: %s', $e->getMessage()));
            Log::debug('Continuing without smart expense matching due to collection failure');
            $this->expenseAccounts   = [];
            $this->revenueAccounts   = [];
            $this->accountsCollected = true; // Mark as collected to avoid repeated failures
        }
    }

    /**
     * Find existing expense or revenue account that matches the transaction description
     */
    private function findExistingAccount(string $description, bool $isDeposit): ?array
    {
        $accountsToSearch      = $isDeposit ? $this->revenueAccounts : $this->expenseAccounts;
        $accountType           = $isDeposit ? 'revenue' : 'expense';

        if (0 === count($accountsToSearch)) {
            Log::debug(sprintf('No %s accounts to search', $accountType));

            return null;
        }

        // Normalize description for matching
        $normalizedDescription = $this->normalizeForMatching($description);

        // Try exact matches first
        foreach ($accountsToSearch as $account) {
            $normalizedAccountName = $this->normalizeForMatching($account['name']);

            // Check for exact match
            if ($normalizedAccountName === $normalizedDescription) {
                Log::debug(sprintf('Exact match found: "%s" -> "%s"', $description, $account['name']));

                return $account;
            }
        }

        // Try fuzzy matching if no exact match found
        $bestMatch             = $this->findBestFuzzyMatch($normalizedDescription, $accountsToSearch);
        if (null !== $bestMatch && [] !== $bestMatch) {
            Log::debug(sprintf('Fuzzy match found: "%s" -> "%s" (similarity: %.2f)', $description, $bestMatch['account']['name'], $bestMatch['similarity']));

            return $bestMatch['account'];
        }

        return null;
    }

    /**
     * Normalize string for matching (lowercase, remove special chars, etc.)
     */
    private function normalizeForMatching(string $text): string
    {
        // Convert to lowercase
        $normalized = strtolower($text);

        // Remove common transaction prefixes/suffixes
        $patterns   = [
            '/^(payment|deposit|transfer|debit|credit)\s+/i',
            '/\s+(payment|deposit|transfer|debit|credit)$/i',
            '/^(from|to)\s+/i',
            '/\s+\d{4}[-\/]\d{2}[-\/]\d{2}.*$/', // Remove trailing dates
            '/\s+#\w+.*$/', // Remove trailing reference numbers
        ];

        foreach ($patterns as $pattern) {
            $normalized = preg_replace($pattern, '', (string) $normalized);
        }

        // Remove special characters and extra spaces
        $normalized = preg_replace('/[^a-z0-9\s]/', '', (string) $normalized);
        $normalized = preg_replace('/\s+/', ' ', (string) $normalized);

        return trim((string) $normalized);
    }

    /**
     * Find best fuzzy match using similarity algorithms
     */
    private function findBestFuzzyMatch(string $normalizedDescription, array $accounts): ?array
    {
        // Check if smart matching is enabled
        if (!config('simplefin.smart_expense_matching', true)) {
            return null;
        }

        $bestMatch      = null;
        $bestSimilarity = 0;
        $threshold      = config('simplefin.expense_matching_threshold', 0.7);

        foreach ($accounts as $account) {
            $normalizedAccountName = $this->normalizeForMatching($account['name']);

            // Calculate similarity using multiple algorithms
            $similarity            = $this->calculateSimilarity($normalizedDescription, $normalizedAccountName);

            if ($similarity > $bestSimilarity && $similarity >= $threshold) {
                $bestSimilarity = $similarity;
                $bestMatch      = [
                    'account'    => $account,
                    'similarity' => $similarity,
                ];
            }
        }

        return $bestMatch;
    }

    /**
     * Calculate similarity between two strings using multiple algorithms
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        // Use Levenshtein distance for similarity
        $maxLen                = max(strlen($str1), strlen($str2));
        if (0 === $maxLen) {
            return 1.0;
        }

        $levenshtein           = levenshtein($str1, $str2);
        $levenshteinSimilarity = 1 - ($levenshtein / $maxLen);

        // Use similar_text for additional comparison
        similar_text($str1, $str2, $percent);
        $similarTextSimilarity = $percent / 100;

        // Check for substring matches (give bonus for contains)
        $substringBonus        = 0;
        if (str_contains($str1, $str2) || str_contains($str2, $str1)) {
            $substringBonus = 0.2;
        }

        // Weighted average of different similarity measures
        $finalSimilarity       = ($levenshteinSimilarity * 0.5) + ($similarTextSimilarity * 0.4) + $substringBonus;

        return min(1.0, $finalSimilarity);
    }

    /**
     * Sanitize description for safe display
     */
    private function sanitizeDescription(string $description): string
    {
        // Remove any potentially harmful characters
        $sanitized = strip_tags($description);
        $sanitized = trim($sanitized);

        // Ensure we have a non-empty description
        if ('' === $sanitized) {
            return 'SimpleFIN Transaction';
        }

        return $sanitized;
    }

    /**
     * Find clustered account name for clean instances without existing accounts
     */
    private function findClusteredAccountName(string $description, bool $isDeposit): ?string
    {
        $accountType                                    = $isDeposit ? 'revenue' : 'expense';
        $normalizedDescription                          = $this->normalizeForMatching($description);
        $threshold                                      = config('simplefin.clustering_similarity_threshold', 0.7);

        // Check existing clusters for similar descriptions
        foreach ($this->pendingTransactionClusters as $clusterName => $cluster) {
            if ($cluster['type'] !== $accountType) {
                continue;
            }

            // Check similarity against cluster representative
            $similarity = $this->calculateSimilarity($normalizedDescription, $cluster['normalized_name']);

            if ($similarity >= $threshold) {
                Log::debug(sprintf(
                    'Clustering "%s" with existing cluster "%s" (similarity: %.2f)',
                    $description,
                    $clusterName,
                    $similarity
                ));

                // Add to existing cluster
                $this->pendingTransactionClusters[$clusterName]['descriptions'][] = $description;
                ++$this->pendingTransactionClusters[$clusterName]['count'];

                return $clusterName;
            }
        }

        // No matching cluster found, create new cluster
        $clusterName                                    = $this->generateClusterName($description);
        $this->pendingTransactionClusters[$clusterName] = [
            'type'            => $accountType,
            'normalized_name' => $normalizedDescription,
            'descriptions'    => [$description],
            'count'           => 1,
            'created_at'      => Carbon::now()->getTimestamp(),
        ];

        Log::debug(sprintf('Created new %s cluster "%s" for "%s"', $accountType, $clusterName, $description));

        return $clusterName;
    }

    /**
     * Generate meaningful cluster name from transaction description
     */
    private function generateClusterName(string $description): string
    {
        // Extract core business/merchant name for clustering
        $cleaned     = $this->extractCounterAccountName($description);

        // Further normalize for cluster naming
        $clusterName = preg_replace('/\b(payment|deposit|transfer|debit|credit|from|to)\b/i', '', $cleaned);
        $clusterName = preg_replace('/\s+/', ' ', trim((string) $clusterName));

        // Remove trailing numbers/references that could vary
        $clusterName = preg_replace('/\s+\d+\s*$/', '', (string) $clusterName);
        $clusterName = preg_replace('/\s+#\w+.*$/', '', (string) $clusterName);

        // Ensure minimum meaningful length
        if (strlen((string) $clusterName) < 3) {
            $clusterName = $cleaned; // Fall back to basic cleaning
        }

        return trim((string) $clusterName);
    }
}
