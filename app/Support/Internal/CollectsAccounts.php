<?php

declare(strict_types=1);
/*
 * CollectsAccounts.php
 * Copyright (c) 2023 james@firefly-iii.org
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

namespace App\Support\Internal;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Nordigen\Model\Account as NordigenAccount;
use App\Services\Nordigen\Request\ListAccountsRequest;
use App\Services\Nordigen\Response\ListAccountsResponse;
use App\Services\Nordigen\Services\AccountInformationCollector;
use App\Services\Nordigen\TokenManager;
use App\Services\Session\Constants;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Spectre\Authentication\SecretManager as SpectreSecretManager;
use App\Services\Spectre\Request\GetAccountsRequest as SpectreGetAccountsRequest;
use App\Services\Spectre\Response\GetAccountsResponse;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use Illuminate\Support\Facades\Cache;

trait CollectsAccounts
{
    /**
     * @return array
     * @throws ApiHttpException
     */
    protected function getFireflyIIIAccounts(): array
    {
        $url             = SecretManager::getBaseUrl();
        $token           = SecretManager::getAccessToken();
        $accounts        = [];

        $request = new GetAccountsRequest($url, $token);
        $request->setType(GetAccountsRequest::ASSET);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $response = $request->get();

        /** @var Account $account */
        foreach ($response as $account) {
            $accounts[Constants::ASSET_ACCOUNTS][$account->id] = $account;
        }

        // also get liabilities
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
        $request = new GetAccountsRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setType(GetAccountsRequest::LIABILITIES);
        $response = $request->get();
        /** @var Account $account */
        foreach ($response as $account) {
            $accounts[Constants::LIABILITIES][$account->id] = $account;
        }

        return $accounts;
    }

    /**
     * List Nordigen accounts with account details, balances, and 2 transactions (if present)
     *
     * @param Configuration $configuration
     *
     * @return array
     * @throws ImporterErrorException|AgreementExpiredException
     */
    protected function getNordigenAccounts(Configuration $configuration): array
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $requisitions = $configuration->getNordigenRequisitions();
        $identifier   = array_shift($requisitions);

        // if cached, return it.
        if (Cache::has($identifier) && config('importer.use_cache')) {
            $result = Cache::get($identifier);
            $return = [];
            foreach ($result as $arr) {
                $return[] = NordigenAccount::fromLocalArray($arr);
            }
            app('log')->debug('Grab accounts from cache', $result);

            return $return;
        }
        // get banks and countries
        $accessToken = TokenManager::getAccessToken();
        $url         = config('nordigen.url');
        $request     = new ListAccountsRequest($url, $identifier, $accessToken);
        $request->setTimeOut(config('importer.connection.timeout'));
        /** @var ListAccountsResponse $response */
        try {
            $response = $request->get();
        } catch (ImporterErrorException|ImporterHttpException $e) {
            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }
        $total  = count($response);
        $return = [];
        $cache  = [];
        app('log')->debug(sprintf('Found %d Nordigen accounts.', $total));

        /** @var NordigenAccount $account */
        foreach ($response as $index => $account) {
            app('log')->debug(
                sprintf('[%d/%d] Now collecting information for account %s', ($index + 1), $total, $account->getIdentifier()),
                $account->toLocalArray()
            );
            $account  = AccountInformationCollector::collectInformation($account);
            $return[] = $account;
            $cache[]  = $account->toLocalArray();
        }
        Cache::put($identifier, $cache, 1800); // half an hour

        return $return;
    }

    /**
     * @param Configuration $configuration
     *
     * @return array
     */
    protected function getSpectreAccounts(Configuration $configuration): array
    {
        $return                  = [];
        $url                     = config('spectre.url');
        $appId                   = SpectreSecretManager::getAppId();
        $secret                  = SpectreSecretManager::getSecret();
        $spectreList             = new SpectreGetAccountsRequest($url, $appId, $secret);
        $spectreList->connection = $configuration->getConnection();
        /** @var GetAccountsResponse $spectreAccounts */
        $spectreAccounts = $spectreList->get();
        foreach ($spectreAccounts as $account) {
            $return[] = $account;
        }

        return $return;
    }
}
