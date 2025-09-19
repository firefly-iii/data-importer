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
use App\Services\Shared\Configuration\Configuration;
use App\Services\SimpleFIN\Request\AccountsRequest;
use Carbon\Carbon;
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
            Log::error('Token is not a base64-encoded claim URL.');

            // Token is not a base64 claim URL, we need an API URL
            throw new ImporterErrorException('Token is not a base64-encoded claim URL.');
        }
    }

    private function getFetchParams(string $accountId): array
    {
        $return     = [
            // 'pending'    => $this->configuration->getPendingTransactions() ? 1 : 0,
            'start-date' => 0,
            'account'    => $accountId,
        ];
        if ($this->configuration->getPendingTransactions()) {
            $return['pending'] = 1;
        }
        $dateAfter  = $this->configuration->getDateNotBefore();
        $dateBefore = $this->configuration->getDateNotAfter();

        // $dateAfter turns into the 'start-date' parameter.
        if ('' !== $dateAfter) {
            // although the data importer uses "Y-m-d", this code should handle most date formats.
            try {
                $carbon               = Carbon::parse($dateAfter, config('app.timezone'));
                $carbon->startOfDay();
                Log::debug(sprintf('Set start-date to %s', $carbon->toW3cString()));
                $return['start-date'] = $carbon->getTimestamp();
            } catch (Exception) {
                Log::error(sprintf('Invalid date format for "dateAfter": %s', $dateAfter));
            }
        }
        if ('' !== $dateBefore) {
            // although the data importer uses "Y-m-d", this code should handle most date formats.
            try {
                $carbon             = Carbon::parse($dateBefore, config('app.timezone'));
                $carbon->endOfDay();
                Log::debug(sprintf('Set end-date to %s', $carbon->toW3cString()));
                $return['end-date'] = $carbon->getTimestamp();
            } catch (Exception) {
                Log::error(sprintf('Invalid date format for "dateBefore": %s', $dateBefore));
            }
        }

        return $return;
    }

    /**
     * @throws ImporterErrorException
     */
    public function fetchAccounts(): array
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        Log::debug(sprintf('SimpleFIN fetching accounts from: %s', $this->accessToken));

        $request    = new AccountsRequest();
        $request->setAccessToken($this->accessToken);
        $request->setTimeOut($this->getTimeout());

        // Set parameters to retrieve all accounts.
        // 2025-07-18 set balances-only to signal to SimpleFIN we only want account info.
        $parameters = [
            'balances-only' => 1,
        ];
        $request->setParameters($parameters);

        Log::debug('SimpleFIN requesting all accounts with parameters', $parameters);

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
     * Downloads transactions for a specific account fresh from the SimpleFIN API.
     * Applies date filtering if specified.
     *
     * @param string $accountId the ID of the account for which to extract transactions
     *
     * @return array list of transaction data (associative arrays from SimpleFIN JSON)
     *
     * @throws ImporterErrorException
     */
    public function fetchFreshTransactions(string $accountId): array
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        Log::debug(sprintf('SimpleFIN download transactions for account ID: "%s" from provided data structure.', $accountId));

        $request      = new AccountsRequest();
        $request->setAccessToken($this->accessToken);
        $request->setTimeOut($this->getTimeout());

        // Set parameters to retrieve all transactions
        // #10599 and #10602 add date information.
        $parameters   = $this->getFetchParams($accountId);
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

        /** @var array $transactions */
        $transactions = $accounts[0]['transactions'] ?? [];

        // add a little filter to remove transactions that are pending.
        $transactions = $this->filterForPending($transactions);

        Log::debug(sprintf('Found %d transactions.', $transactions));

        return $transactions;
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

            $parts     = parse_url($claimUrl);
            Log::debug(sprintf('Parsed $claimUrl parts: %s', json_encode($parts)));
            $headers   = [
                'Content-Length' => '0',
                // 'Origin' => sprintf('%s://%s', $parts['scheme'] ?? 'https', $parts['host'] ?? 'localhost'),
                'User-Agent'     => sprintf('FF3-data-importer/%s (%s)', config('importer.version'), config('importer.line_d')),
            ];
            Log::debug('Headers for claim URL exchange', $headers);

            $response  = $client->post($claimUrl, [
                'headers' => $headers,
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

    private function filterForPending(array $transactions): array
    {
        Log::debug(sprintf('Filter pending transactions. Start with %d item(s)', count($transactions)));
        if (0 === count($transactions)) {
            Log::debug('Empty array, nothing to filter.');

            return [];
        }
        $return        = [];
        $removePending = !$this->configuration->getPendingTransactions();

        /** @var array $item */
        foreach ($transactions as $item) {
            $add = true;
            // is pending and need to collect pending transactions? add it.
            if (array_key_exists('pending', $item) && true === $item['pending'] && $removePending) {
                Log::debug('Transaction is pending and removePending is true, skipping.');
                $add = false;
            }
            if (true === $add) {
                Log::debug('Transaction is not pending or removePending = false, add it.');
                $return[] = $item;
            }
        }
        Log::debug(sprintf('Done filtering pending transaction, return %d item(s)', count($return)));

        return $return;
    }
}
