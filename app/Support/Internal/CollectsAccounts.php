<?php

/*
 * CollectsAccounts.php
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

namespace App\Support\Internal;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Nordigen\Model\Account as NordigenAccount;
use App\Services\Nordigen\Request\ListAccountsRequest;
use App\Services\Nordigen\Services\AccountInformationCollector;
use App\Services\Nordigen\TokenManager;
use App\Services\Session\Constants;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Spectre\Authentication\SecretManager as SpectreSecretManager;
use App\Services\LunchFlow\Authentication\SecretManager as LunchFlowSecretManager;
use App\Services\Spectre\Request\GetAccountsRequest as SpectreGetAccountsRequest;
use App\Services\LunchFlow\Request\GetAccountsRequest as LunchFlowGetAccountsRequest;
use App\Services\Spectre\Response\GetAccountsResponse;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

trait CollectsAccounts
{
    /**
     * @throws ApiHttpException
     */
    protected function getFireflyIIIAccounts(): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        $accounts = [
            Constants::ASSET_ACCOUNTS => [],
            Constants::LIABILITIES    => [],
        ];
        $url      = null;

        try {
            $url               = SecretManager::getBaseUrl();
            $token             = SecretManager::getAccessToken();

            if ('' === $url || '' === $token) {
                Log::error('Base URL or Access Token is empty. Cannot fetch accounts.', ['url_empty' => '' === $url, 'token_empty' => '' === $token]);

                return $accounts; // Return empty accounts if auth details are missing
            }

            // Fetch ASSET accounts
            Log::debug('Fetching asset accounts from Firefly III.', ['url' => $url]);
            $requestAsset      = new GetAccountsRequest($url, $token);
            $requestAsset->setType(GetAccountsRequest::ASSET);
            $requestAsset->setVerify(config('importer.connection.verify'));
            $requestAsset->setTimeOut(config('importer.connection.timeout'));
            $responseAsset     = $requestAsset->get();

            /** @var Account $account */
            foreach ($responseAsset as $account) {
                $accounts[Constants::ASSET_ACCOUNTS][$account->id] = $account;
            }
            Log::debug(sprintf('Fetched %d asset accounts.', count($accounts[Constants::ASSET_ACCOUNTS])));

            // Fetch LIABILITY accounts
            // URL and token are likely the same, but re-fetching defensively or if SecretManager has internal state
            $url               = SecretManager::getBaseUrl(); // Re-fetch in case of any state change, though unlikely
            $token             = SecretManager::getAccessToken();

            if ('' === $url || '' === $token) { // Check again, though highly unlikely to change if first call succeeded.
                Log::error('Base URL or Access Token became empty before fetching LIABILITY accounts.');

                return $accounts; // Return partially filled or empty accounts
            }

            Log::debug('Fetching liability accounts from Firefly III.', ['url' => $url]);
            $requestLiability  = new GetAccountsRequest($url, $token);
            $requestLiability->setVerify(config('importer.connection.verify'));
            $requestLiability->setTimeOut(config('importer.connection.timeout'));
            $requestLiability->setType(GetAccountsRequest::LIABILITIES);
            $responseLiability = $requestLiability->get();

            /** @var Account $account */
            foreach ($responseLiability as $account) {
                $accounts[Constants::LIABILITIES][$account->id] = $account;
            }
            Log::debug(sprintf('Fetched %d liability accounts.', count($accounts[Constants::LIABILITIES])));

        } catch (ApiHttpException $e) {
            Log::error('ApiHttpException while fetching Firefly III accounts.', [
                'message' => $e->getMessage(),
                'code'    => $e->getCode(),
                'url'     => $url, // Log URL that might have caused issue
                'trace'   => $e->getTraceAsString(),
            ]);
            // Return the (potentially partially filled) $accounts array so the app doesn't hard crash.
            // The view should handle cases where account lists are empty.
        } catch (Exception $e) {
            Log::error('Generic Exception while fetching Firefly III accounts.', [
                'message' => $e->getMessage(),
                'code'    => $e->getCode(),
                'url'     => $url,
                'trace'   => $e->getTraceAsString(),
            ]);
        }
        // Log::debug('CollectsAccounts::getFireflyIIIAccounts - Returning accounts structure.', $accounts);

        return $accounts;
    }

    /**
     * @throws GuzzleException
     * @throws ImporterHttpException
     */
    protected function getSpectreAccounts(Configuration $configuration): array
    {
        $return                  = [];
        $url                     = config('spectre.url');
        $appId                   = SpectreSecretManager::getAppId();
        $secret                  = SpectreSecretManager::getSecret();
        $spectreList             = new SpectreGetAccountsRequest($url, $appId, $secret);
        $spectreList->setTimeOut(config('importer.connection.timeout'));
        $spectreList->connection = $configuration->getConnection();

        /** @var GetAccountsResponse $spectreAccounts */
        $spectreAccounts         = $spectreList->get();
        foreach ($spectreAccounts as $account) {
            $return[] = $account;
        }

        return $return;
    }

    /**
     * @throws GuzzleException
     * @throws ImporterHttpException
     */
    protected function getLunchFlowAccounts(Configuration $configuration): array
    {
        $return                  = [];
        $apiKey                   = LunchFlowSecretManager::getApiKey($configuration);
        $lunchFlowList             = new LunchFlowGetAccountsRequest($apiKey);
        $lunchFlowList->setTimeOut(config('importer.connection.timeout'));

        /** @var GetAccountsResponse $lunchFlowAccounts */
        $lunchFlowAccounts         = $lunchFlowList->get();
        foreach ($lunchFlowAccounts as $account) {
            $return[] = $account;
        }

        return $return;
    }
}
