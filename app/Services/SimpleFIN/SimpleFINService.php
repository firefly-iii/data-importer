<?php

/*
 * SimpleFINService.php
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

namespace App\Services\SimpleFIN;

use App\Exceptions\ImporterErrorException;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\SimpleFIN\Request\AccountsRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use App\Services\SimpleFIN\Request\TransactionsRequest;

/**
 * Class SimpleFINService
 */
class SimpleFINService
{
    /**
     * @throws ImporterHttpException
     * @throws ImporterErrorException
     */
    public function fetchAccountsAndInitialData(string $token, string $apiUrl, ?Configuration $configuration = null): array
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));

        // Check if token is a base64-encoded claim URL
        $actualApiUrl = $apiUrl;
        $actualToken = $token;

        if ($this->isBase64ClaimUrl($token)) {
            app('log')->debug('Token appears to be a base64-encoded claim URL, processing exchange');
            $actualApiUrl = $this->exchangeClaimUrlForAccessUrl($token);
            $actualToken = ''; // Access URL contains auth info
            app('log')->debug(sprintf('Successfully exchanged claim URL for access URL: %s', $actualApiUrl));
        } else {
            // Token is not a base64 claim URL, we need an API URL
            if (empty($apiUrl)) {
                throw new ImporterErrorException('SimpleFIN API URL is required when token is not a base64-encoded claim URL');
            }
        }

        app('log')->debug(sprintf('SimpleFIN fetching accounts from: %s', $actualApiUrl));

        $request = new AccountsRequest();
        $request->setToken($actualToken);
        $request->setApiUrl($actualApiUrl);
        $request->setTimeOut($this->getTimeout());

        // Set parameters to retrieve all transactions
        // Use a very old start-date (Jan 1, 2000) to ensure we get all historical transactions
        $parameters = [
            'start-date' => 946684800, // January 1, 2000 00:00:00 UTC
            'pending' => ($configuration && $configuration->getPendingTransactions()) ? 1 : 0,
        ];
        $request->setParameters($parameters);

        app('log')->debug('SimpleFIN requesting all transactions with parameters', $parameters);

        $response = $request->get();

        if ($response->hasError()) {
            throw new ImporterErrorException(sprintf('SimpleFIN API error: HTTP %d', $response->getStatusCode()));
        }

        $accounts = $response->getAccounts();

        if (empty($accounts)) {
            app('log')->warning('SimpleFIN API returned no accounts');
            return [];
        }

        app('log')->debug(sprintf('SimpleFIN fetched %d accounts successfully', count($accounts)));

        return $accounts;
    }

    /**
     * Extracts transactions for a specific account from the pre-fetched SimpleFIN accounts data.
     * Applies date filtering if specified.
     *
     * @param array $allAccountsData Array of account data (associative arrays from SimpleFIN JSON).
     * @param string $accountId The ID of the account for which to extract transactions.
     * @param array|null $dateRange Optional date range for filtering transactions. Expects ['start' => 'Y-m-d', 'end' => 'Y-m-d'].
     * @return array List of transaction data (associative arrays from SimpleFIN JSON).
     */
    public function fetchTransactions(array $allAccountsData, string $accountId, ?array $dateRange = null): array
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        app('log')->debug(sprintf('SimpleFIN extracting transactions for account ID: "%s" from provided data structure.', $accountId));

        $accountTransactions = [];
        $accountFound = false;

        foreach ($allAccountsData as $accountData) {
            // $accountData is now an associative array from the SimpleFIN JSON response.
            // Ensure $accountData is an array and has an 'id' key before accessing.
            if (is_array($accountData) && isset($accountData['id']) && is_string($accountData['id']) && $accountData['id'] === $accountId) {
                $accountFound = true;
                // Transactions are expected to be in $accountData['transactions'] as an array
                if (isset($accountData['transactions']) && is_array($accountData['transactions'])) {
                    $accountTransactions = $accountData['transactions'];
                } else {
                    // If 'transactions' key is missing or not an array, treat as no transactions.
                    $accountTransactions = [];
                }
                break;
            }
        }

        if (!$accountFound) {
            app('log')->warning(sprintf('Account with ID "%s" not found in provided SimpleFIN accounts data.', $accountId));
            return [];
        }

        if (empty($accountTransactions)) {
            app('log')->debug(sprintf('No transactions found internally for account ID "%s".', $accountId));
            return [];
        }

        // Apply date range filtering
        $filteredTransactions = [];
        if (!empty($dateRange) && (isset($dateRange['start']) || isset($dateRange['end']))) {
            $startDateTimestamp = null;
            $endDateTimestamp = null;

            if (isset($dateRange['start']) && !empty($dateRange['start'])) {
                try {
                    $startDateTimestamp = (new \DateTime($dateRange['start'], new \DateTimeZone('UTC')))->setTime(0,0,0)->getTimestamp();
                } catch (\Exception $e) {
                    app('log')->warning('Invalid start date format for SimpleFIN transaction filtering.', ['date' => $dateRange['start'], 'error' => $e->getMessage()]);
                }
            }
            if (isset($dateRange['end']) && !empty($dateRange['end'])) {
                try {
                    $endDateTimestamp = (new \DateTime($dateRange['end'], new \DateTimeZone('UTC')))->setTime(23, 59, 59)->getTimestamp();
                } catch (\Exception $e) {
                    app('log')->warning('Invalid end date format for SimpleFIN transaction filtering.', ['date' => $dateRange['end'], 'error' => $e->getMessage()]);
                }
            }

            foreach ($accountTransactions as $transaction) {
                // $transaction is now an associative array from the SimpleFIN JSON response.
                // Ensure $transaction is an array and has a 'posted' key before accessing.
                if (!is_array($transaction) || !isset($transaction['posted']) || !is_numeric($transaction['posted'])) {
                    $transactionIdForLog = (is_array($transaction) && isset($transaction['id']) && is_string($transaction['id'])) ? $transaction['id'] : 'unknown';
                    app('log')->warning('Transaction array missing, not an array, or has invalid "posted" field.', ['transaction_id' => $transactionIdForLog, 'transaction_data' => $transaction]);
                    continue;
                }
                $postedTimestamp = (int)$transaction['posted']; // Ensure it's an integer for comparison
                $passesFilter = true;

                if ($startDateTimestamp !== null && $postedTimestamp < $startDateTimestamp) {
                    $passesFilter = false;
                }
                if ($endDateTimestamp !== null && $postedTimestamp > $endDateTimestamp) {
                    $passesFilter = false;
                }

                if ($passesFilter) {
                    $filteredTransactions[] = $transaction;
                }
            }
            app('log')->debug(sprintf('Applied date filtering. Start: %s, End: %s. Original count: %d, Filtered count: %d',
                $dateRange['start'] ?? 'N/A', $dateRange['end'] ?? 'N/A', count($accountTransactions), count($filteredTransactions)
            ));
        } else {
            $filteredTransactions = $accountTransactions;
        }

        app('log')->debug(sprintf('SimpleFIN extracted %d transactions for account ID "%s" (after potential filtering).', count($filteredTransactions), $accountId));
        return $filteredTransactions;
    }

    /**
     * Test connectivity to SimpleFIN API with given credentials
     */
    public function testConnection(string $token, string $apiUrl): bool
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));

        try {
            $accounts = $this->fetchAccountsAndInitialData($token, $apiUrl);
            app('log')->debug('SimpleFIN connection test successful');
            return true;
        } catch (ImporterHttpException|ImporterErrorException $e) {
            app('log')->error(sprintf('SimpleFIN connection test failed: %s', $e->getMessage()));
            return false;
        }
    }

    /**
     * Get demo credentials for testing
     */
    public function getDemoCredentials(): array
    {
        return [
            'token' => config('importer.simplefin.demo_token'),
            'url' => config('importer.simplefin.demo_url'),
        ];
    }

    /**
     * Check if a token is a base64-encoded claim URL
     */
    private function isBase64ClaimUrl(string $token): bool
    {
        // Try to decode as base64
        $decoded = base64_decode($token, true);

        // Check if decode was successful and result looks like a URL
        if ($decoded === false) {
            return false;
        }

        // Check if decoded string looks like a SimpleFIN claim URL
        return (bool) preg_match('/^https?:\/\/.+\/simplefin\/claim\/.+$/', $decoded);
    }

    /**
     * Exchange a base64-encoded claim URL for an access URL
     *
     * @throws ImporterErrorException
     */
    private function exchangeClaimUrlForAccessUrl(string $base64ClaimUrl): string
    {
        app('log')->debug('Exchanging SimpleFIN claim URL for access URL');

        // Decode the base64 claim URL
        $claimUrl = base64_decode($base64ClaimUrl, true);
        if ($claimUrl === false) {
            throw new ImporterErrorException('Invalid base64 encoding in SimpleFIN token');
        }

        app('log')->debug(sprintf('Decoded claim URL: %s', $claimUrl));

        try {
            $client = new Client([
                'timeout' => $this->getTimeout(),
                'verify' => config('importer.connection.verify'),
            ]);

            // Make POST request to claim URL with empty body
            // Use user-provided bridge URL as Origin header for CORS
            $origin = session()->get(Constants::SIMPLEFIN_BRIDGE_URL);
            if (empty($origin)) {
                throw new ImporterErrorException('SimpleFIN bridge URL not found in session. Please provide a valid bridge URL.');
            }
            app('log')->debug(sprintf('SimpleFIN using user-provided Origin: %s', $origin));

            $response = $client->post($claimUrl, [
                'headers' => [
                    'Content-Length' => '0',
                    'Origin' => $origin,
                ],
            ]);

            $accessUrl = (string) $response->getBody();

            if (empty($accessUrl)) {
                throw new ImporterErrorException('Empty access URL returned from SimpleFIN claim exchange');
            }

            // Validate access URL format
            if (!filter_var($accessUrl, FILTER_VALIDATE_URL)) {
                throw new ImporterErrorException('Invalid access URL format returned from SimpleFIN claim exchange');
            }

            app('log')->debug('Successfully exchanged claim URL for access URL');
            return $accessUrl;

        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();

            app('log')->error(sprintf('SimpleFIN claim URL exchange failed with HTTP %d: %s', $statusCode, $e->getMessage()));
            app('log')->error(sprintf('SimpleFIN 403 response body: %s', $responseBody));

            if ($statusCode === 403) {
                // Log the actual response for debugging
                app('log')->error(sprintf('DETAILED 403 ERROR - URL: %s, Response: %s', $claimUrl, $responseBody));
                throw new ImporterErrorException(sprintf('SimpleFIN claim URL exchange failed (403 Forbidden): %s', $responseBody ?: 'No response body available'));
            }

            throw new ImporterErrorException(sprintf('Failed to exchange SimpleFIN claim URL: HTTP %d error - %s', $statusCode, $responseBody ?: $e->getMessage()));
        } catch (GuzzleException $e) {
            app('log')->error(sprintf('Failed to exchange SimpleFIN claim URL: %s', $e->getMessage()));
            throw new ImporterErrorException(sprintf('Failed to exchange SimpleFIN claim URL: %s', $e->getMessage()));
        }
    }

    /**
     * Validate SimpleFIN credentials format
     */
    public function validateCredentials(string $token, string $apiUrl): array
    {
        $errors = [];

        if (empty($token)) {
            $errors[] = 'SimpleFIN token is required';
        }

        if (empty($apiUrl)) {
            $errors[] = 'SimpleFIN bridge URL is required';
        } elseif (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'SimpleFIN bridge URL must be a valid URL';
        } elseif (!str_starts_with($apiUrl, 'https://')) {
            $errors[] = 'SimpleFIN bridge URL must use HTTPS';
        }

        return $errors;
    }

    private function getTimeout(): float
    {
        return (float) config('importer.simplefin.timeout', 30.0);
    }
}