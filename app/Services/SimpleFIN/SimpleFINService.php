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
use App\Exceptions\ImporterHttpException;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\SimpleFIN\Request\AccountsRequest;
use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Class SimpleFINService
 */
class SimpleFINService
{
    private string        $accessToken = '';
    private string        $accessUrl   = '';
    private string        $bridgeUrl   = '';
    private string        $setupToken  = '';
    private Configuration $configuration;

    /**
     * @throws ImporterErrorException
     */
    public function exchangeSetupTokenForAccessToken(): void
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        if ('' !== $this->accessToken) {
            Log::warning('Access token already set, skipping exchange.');

            return;
        }
        $isValid = $this->isBase64ClaimUrl($this->setupToken);
        if ($isValid) {
            Log::debug('Token appears to be a base64-encoded setup token, processing exchange');
            $this->accessToken = $this->exchangeClaimUrlForAccessUrl($this->setupToken);
            Log::debug(sprintf('Successfully exchanged claim URL for access token: %s', $this->accessToken));
        }
        if (!$isValid) {
            Log::debug('Token is not a base64-encoded claim URL, using provided bridge URL');
            // Token is not a base64 claim URL, we need an API URL
            if ('' === $this->bridgeUrl) {
                throw new ImporterErrorException('SimpleFIN API URL is required when token is not a base64-encoded claim URL');
            }
        }
    }

    private function getTransactions(string $accountId): array
    {
        // account
        Log::debug(sprintf('Now at %s', __METHOD__));
        Log::debug(sprintf('SimpleFIN fetching transactions from: %s for account %s', $this->accessToken, $accountId));

        $request    = new AccountsRequest();
        $request->setBridgeUrl($this->bridgeUrl);
        $request->setAccessToken($this->accessToken);
        $request->setTimeOut($this->getTimeout());

        var_dump($this->configuration->getDateRange());

        exit;

        // Set parameters to retrieve all transactions
        // 2025-07-05 set date to the far future, because here we are not interested in any transactions.
        $parameters = [
            'start-date' => 2073594480, // Sept 17, 2035 12:28 GMT+2
            'pending'    => 0,
            'account'    => $accountId,
        ];
        $request->setParameters($parameters);

        Log::debug('SimpleFIN requesting all transactions with parameters', $parameters);

        try {
            $response = $request->get();
        } catch (ImporterHttpException $e) {
            throw new ImporterErrorException($e->getMessage(), $e->getCode(), $e);
        }

        if ($response->hasError()) {
            throw new ImporterErrorException(sprintf('SimpleFIN API error: HTTP %d', $response->getStatusCode()));
        }

        $accounts   = $response->getAccounts();

        if (0 === count($accounts)) {
            Log::warning('SimpleFIN API returned no accounts');

            return [];
        }

        Log::debug(sprintf('SimpleFIN fetched %d accounts successfully', count($accounts)));

        return $accounts;
    }

    /**
     * @throws ImporterErrorException
     */
    public function fetchAccounts(): array
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        Log::debug(sprintf('SimpleFIN fetching accounts from: %s', $this->accessToken));

        $request    = new AccountsRequest();
        $request->setBridgeUrl($this->bridgeUrl);
        $request->setAccessToken($this->accessToken);
        $request->setTimeOut($this->getTimeout());

        // Set parameters to retrieve all transactions
        // 2025-07-05 set date to the far future, because here we are not interested in any transactions.
        $parameters = [
            'start-date' => 2073594480, // Sept 17, 2035 12:28 GMT+2
            'pending'    => 0,
        ];
        $request->setParameters($parameters);

        Log::debug('SimpleFIN requesting all transactions with parameters', $parameters);

        try {
            $response = $request->get();
        } catch (ImporterHttpException $e) {
            throw new ImporterErrorException($e->getMessage(), $e->getCode(), $e);
        }

        if ($response->hasError()) {
            throw new ImporterErrorException(sprintf('SimpleFIN API error: HTTP %d', $response->getStatusCode()));
        }

        $accounts   = $response->getAccounts();

        if (0 === count($accounts)) {
            Log::warning('SimpleFIN API returned no accounts');

            return [];
        }

        Log::debug(sprintf('SimpleFIN fetched %d accounts successfully', count($accounts)));

        return $accounts;
    }

    /**
     * Extracts transactions for a specific account from the pre-fetched SimpleFIN accounts data.
     * Applies date filtering if specified.
     *
     * @param array      $allAccountsData array of account data (associative arrays from SimpleFIN JSON)
     * @param string     $accountId       the ID of the account for which to extract transactions
     * @param null|array $dateRange       Optional date range for filtering transactions. Expects ['start' => 'Y-m-d', 'end' => 'Y-m-d'].
     *
     * @return array list of transaction data (associative arrays from SimpleFIN JSON)
     */
    public function fetchTransactions(array $allAccountsData, string $accountId, ?array $dateRange = null): array
    {
        exit('do not use this method.');
        Log::debug(sprintf('Now at %s', __METHOD__));
        Log::debug(sprintf('SimpleFIN extracting transactions for account ID: "%s" from provided data structure.', $accountId));

        $accountTransactions  = [];
        $accountFound         = false;

        foreach ($allAccountsData as $accountData) {
            // $accountData is now an associative array from the SimpleFIN JSON response.
            // Ensure $accountData is an array and has an 'id' key before accessing.
            if (is_array($accountData) && isset($accountData['id']) && is_string($accountData['id']) && $accountData['id'] === $accountId) {
                Log::debug(sprintf('Found account array for account ID #%s', $accountData['id']));
                $accountFound        = true;
                // Transactions are expected to be in $accountData['transactions'] as an array
                $accountTransactions = [];

                if (isset($accountData['transactions']) && is_array($accountData['transactions'])) {
                    Log::debug(sprintf('Have %d transactions in array.', count($accountData['transactions'])));
                    $accountTransactions = $accountData['transactions'];
                }
                if (0 === count($accountTransactions)) {
                    Log::debug('Have no transactions in array, need to download them.');
                }

                break;
            }
        }

        if (!$accountFound) {
            Log::warning(sprintf('Account with ID "%s" not found in provided SimpleFIN accounts data.', $accountId));

            return [];
        }

        if (0 === count($accountTransactions)) {
            Log::debug(sprintf('No transactions found internally for account ID "%s".', $accountId));

            return [];
        }

        // Apply date range filtering
        $filteredTransactions = [];
        if (is_array($dateRange) && (isset($dateRange['start']) || isset($dateRange['end']))) {
            $startDateTimestamp = null;
            $endDateTimestamp   = null;

            if (isset($dateRange['start']) && '' !== (string)$dateRange['start']) {
                try {
                    $startDateTimestamp = new DateTime($dateRange['start'], new DateTimeZone('UTC'))->setTime(0, 0, 0)->getTimestamp();
                } catch (Exception $e) {
                    Log::warning('Invalid start date format for SimpleFIN transaction filtering.', ['date' => $dateRange['start'], 'error' => $e->getMessage()]);
                }
            }
            if (isset($dateRange['end']) && '' !== (string)$dateRange['end']) {
                try {
                    $endDateTimestamp = new DateTime($dateRange['end'], new DateTimeZone('UTC'))->setTime(23, 59, 59)->getTimestamp();
                } catch (Exception $e) {
                    Log::warning('Invalid end date format for SimpleFIN transaction filtering.', ['date' => $dateRange['end'], 'error' => $e->getMessage()]);
                }
            }

            foreach ($accountTransactions as $transaction) {
                // $transaction is now an associative array from the SimpleFIN JSON response.
                // Ensure $transaction is an array and has a 'posted' key before accessing.
                if (!is_array($transaction) || !isset($transaction['posted']) || !is_numeric($transaction['posted'])) {
                    $transactionIdForLog = (is_array($transaction) && isset($transaction['id']) && is_string($transaction['id'])) ? $transaction['id'] : 'unknown';
                    Log::warning('Transaction array missing, not an array, or has invalid "posted" field.', ['transaction_id' => $transactionIdForLog, 'transaction_data' => $transaction]);

                    continue;
                }
                $postedTimestamp = (int)$transaction['posted']; // Ensure it's an integer for comparison
                $passesFilter    = true;

                if (null !== $startDateTimestamp && $postedTimestamp < $startDateTimestamp) {
                    $passesFilter = false;
                }
                if (null !== $endDateTimestamp && $postedTimestamp > $endDateTimestamp) {
                    $passesFilter = false;
                }

                if ($passesFilter) {
                    $filteredTransactions[] = $transaction;
                }
            }
            Log::debug(sprintf(
                'Applied date filtering. Start: %s, End: %s. Original count: %d, Filtered count: %d',
                $dateRange['start'] ?? 'N/A',
                $dateRange['end'] ?? 'N/A',
                count($accountTransactions),
                count($filteredTransactions)
            ));
            Log::debug(sprintf('SimpleFIN extracted %d transactions for account ID "%s" (after potential filtering).', count($filteredTransactions), $accountId));

            return $filteredTransactions;
        }
        $filteredTransactions = $accountTransactions;

        Log::debug(sprintf('SimpleFIN extracted %d transactions for account ID "%s" (no date filtering was applied).', count($filteredTransactions), $accountId));

        return $filteredTransactions;
    }

    /**
     * Downloads transactions for a specific account from the pre-fetched SimpleFIN accounts data.
     * Applies date filtering if specified.
     *
     * @param string     $accountId the ID of the account for which to extract transactions
     * @param null|array $dateRange Optional date range for filtering transactions. Expects ['start' => 'Y-m-d', 'end' => 'Y-m-d'].
     *
     * @return array list of transaction data (associative arrays from SimpleFIN JSON)
     */
    public function fetchFreshTransactions(string $accountId, ?array $dateRange = null): array
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        Log::debug(sprintf('SimpleFIN download transactions for account ID: "%s" from provided data structure.', $accountId));

        $request      = new AccountsRequest();
        $request->setBridgeUrl($this->bridgeUrl);
        $request->setAccessToken($this->accessToken);
        $request->setTimeOut($this->getTimeout());

        // Set parameters to retrieve all transactions
        // 2025-07-05 set date to the far future, because here we are not interested in any transactions.
        $parameters   = [
            'pending' => $this->configuration->getPendingTransactions() ? 1 : 0,
        ];

        if (null !== $dateRange) {
            if (array_key_exists('start', $dateRange) && '' !== (string)$dateRange['start']) {
                try {
                    $startDateTimestamp       = new DateTime($dateRange['start'], new DateTimeZone('UTC'))->setTime(0, 0, 0)->getTimestamp();
                    $parameters['start-date'] = $startDateTimestamp;
                } catch (Exception $e) {
                    Log::warning('Invalid start date format for SimpleFIN transaction filtering.', ['date' => $dateRange['start'], 'error' => $e->getMessage()]);
                }
            }
            if (array_key_exists('end', $dateRange) && '' !== (string)$dateRange['end']) {
                try {
                    $startDateTimestamp     = new DateTime($dateRange['end'], new DateTimeZone('UTC'))->setTime(23, 59, 59)->getTimestamp();
                    $parameters['end-date'] = $startDateTimestamp;
                } catch (Exception $e) {
                    Log::warning('Invalid end date format for SimpleFIN transaction filtering.', ['date' => $dateRange['end'], 'error' => $e->getMessage()]);
                }
            }
        }
        $request->setParameters($parameters);

        Log::debug('SimpleFIN downloading all transactions with parameters', $parameters);

        try {
            $response = $request->get();
        } catch (ImporterHttpException $e) {
            throw new ImporterErrorException($e->getMessage(), $e->getCode(), $e);
        }

        if ($response->hasError()) {
            throw new ImporterErrorException(sprintf('SimpleFIN API error: HTTP %d', $response->getStatusCode()));
        }

        $accounts     = $response->getAccounts();

        if (0 === count($accounts)) {
            Log::warning('SimpleFIN API returned no accounts');

            return [];
        }
        $transactions = $accounts[0]['transactions'] ?? [];
        Log::debug(sprintf('Found %d transactions.', $transactions));

        return $transactions;
    }

    /**
     * Test connectivity to SimpleFIN API with given credentials
     */
    public function testConnection(string $token, string $apiUrl): bool
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        try {
            $accounts = $this->fetchAccountsAndInitialData($token, $apiUrl);
            Log::debug(sprintf('[%s] SimpleFIN connection test successful', config('importer.version')));

            return true;
        } catch (ImporterErrorException|ImporterHttpException $e) {
            Log::error(sprintf('[%s] SimpleFIN connection test failed: %s', config('importer.version'), $e->getMessage()));

            return false;
        }
    }

    /**
     * Get demo credentials for testing
     */
    public function getDemoCredentials(): array
    {
        return [
            'token' => config('simplefin.demo_token'),
            'url'   => config('simplefin.demo_url'),
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
        if (false === $decoded) {
            return false;
        }

        // Check if decoded string looks like a SimpleFIN claim URL
        return (bool)preg_match('/^https?:\/\/.+\/simplefin\/claim\/.+$/', $decoded);
    }

    /**
     * Exchange a base64-encoded claim URL for an access URL
     *
     * @throws ImporterErrorException
     */
    private function exchangeClaimUrlForAccessUrl(string $base64ClaimUrl): string
    {
        Log::debug('Exchanging SimpleFIN claim URL for access URL');

        // Decode the base64 claim URL
        $claimUrl = base64_decode($base64ClaimUrl, true);
        if (false === $claimUrl) {
            throw new ImporterErrorException('Invalid base64 encoding in SimpleFIN token');
        }

        Log::debug(sprintf('Decoded claim URL: %s', $claimUrl));

        try {
            $client    = new Client([
                'timeout' => $this->getTimeout(),
                'verify'  => config('importer.connection.verify'),
            ]);

            // Make POST request to claim URL with empty body
            // Use user-provided bridge URL as Origin header for CORS
            $origin    = (string)session()->get(Constants::SIMPLEFIN_BRIDGE_URL);
            if ('' === $origin) {
                throw new ImporterErrorException('SimpleFIN bridge URL not found in session. Please provide a valid bridge URL.');
            }
            Log::debug(sprintf('SimpleFIN using user-provided Origin: %s', $origin));

            $response  = $client->post($claimUrl, [
                'headers' => [
                    'Content-Length' => '0',
                    'Origin'         => $origin,
                ],
            ]);

            $accessUrl = (string)$response->getBody();

            if ('' === $accessUrl) {
                throw new ImporterErrorException('Empty access URL returned from SimpleFIN claim exchange');
            }

            // Validate access URL format
            if (!filter_var($accessUrl, FILTER_VALIDATE_URL)) {
                throw new ImporterErrorException('Invalid access URL format returned from SimpleFIN claim exchange');
            }

            Log::debug('Successfully exchanged claim URL for access URL');

            return $accessUrl;

        } catch (ClientException $e) {
            $statusCode   = $e->getResponse()->getStatusCode();
            $responseBody = (string)$e->getResponse()->getBody();

            Log::error(sprintf('SimpleFIN claim URL exchange failed with HTTP %d: %s', $statusCode, $e->getMessage()));
            Log::error(sprintf('SimpleFIN 403 response body: %s', $responseBody));

            if (403 === $statusCode) {
                // Log the actual response for debugging
                Log::error(sprintf('DETAILED 403 ERROR - URL: %s, Response: %s', $claimUrl, $responseBody));

                throw new ImporterErrorException(sprintf('SimpleFIN claim URL exchange failed (403 Forbidden): %s', '' !== $responseBody && '0' !== $responseBody ? $responseBody : 'No response body available'));
            }

            throw new ImporterErrorException(sprintf('Failed to exchange SimpleFIN claim URL: HTTP %d error - %s', $statusCode, '' !== $responseBody && '0' !== $responseBody ? $responseBody : $e->getMessage()));
        } catch (GuzzleException $e) {
            Log::error(sprintf('Failed to exchange SimpleFIN claim URL: %s', $e->getMessage()));

            throw new ImporterErrorException(sprintf('Failed to exchange SimpleFIN claim URL: %s', $e->getMessage()));
        }
    }

    /**
     * Validate SimpleFIN credentials format
     */
    public function validateCredentials(string $token, string $apiUrl): array
    {
        $errors = [];

        if ('' === $token) {
            $errors[] = 'SimpleFIN token is required';
        }

        if ('' === $apiUrl) {
            $errors[] = 'SimpleFIN bridge URL is required';
        }
        if ('' !== $apiUrl && !filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'SimpleFIN bridge URL must be a valid URL';
        }
        if ('' !== $apiUrl && !str_starts_with($apiUrl, 'https://')) {
            $errors[] = 'SimpleFIN bridge URL must use HTTPS';
        }

        return $errors;
    }

    private function getTimeout(): float
    {
        return (float)config('simplefin.connection_timeout', 30.0);
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setBridgeUrl(string $bridgeUrl): void
    {
        $this->bridgeUrl = $bridgeUrl;
    }

    public function setSetupToken(string $setupToken): void
    {
        $this->setupToken = $setupToken;
    }

    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
        $this->accessToken   = $configuration->getAccessToken();
    }

    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }
}
