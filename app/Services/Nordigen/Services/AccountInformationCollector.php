<?php
/*
 * AccountInformationCollector.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Nordigen importer
 * (https://github.com/firefly-iii/nordigen-importer).
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

namespace App\Services\Nordigen\Services;

use App\Services\Nordigen\Model\Account;
use App\Services\Nordigen\Model\Balance;
use App\Services\Nordigen\Request\GetAccountBalanceRequest;
use App\Services\Nordigen\Request\GetAccountInformationRequest;
use App\Services\Nordigen\Response\ArrayResponse;
use App\Services\Nordigen\TokenManager;
use Log;

/**
 * Class AccountInformationCollector
 *
 * Collects meta information and more on the given Account
 */
class AccountInformationCollector
{
    /**
     * @param Account $account
     * @return Account
     */
    public static function collectInformation(Account $account): Account
    {
        Log::debug(sprintf('Now in %s', __METHOD__));

        // you know nothing, Jon Snow
        $account = self::getAccountDetails($account);
        return self::getBalanceDetails($account);
    }

    /**
     * @param Account $account
     * @return Account
     */
    protected static function getAccountDetails(Account $account): Account
    {
        Log::debug(sprintf('Now in %s(%s)', __METHOD__, $account->getIdentifier()));

        $url         = config('nordigen.url');
        $accessToken = TokenManager::getAccessToken();
        $request     = new GetAccountInformationRequest($url, $accessToken, $account->getIdentifier());
        /** @var ArrayResponse $response */
        $response    = $request->get();
        $information = $response->data['account'];
        Log::debug('Collected information for account', $information);

        $account->setResourceId($information['resource_id'] ?? '');
        $account->setBban($information['bban'] ?? '');
        $account->setBic($information['bic'] ?? '');
        $account->setCashAccountType($information['cashAccountType'] ?? '');
        $account->setCurrency($information['currency'] ?? '');

        $account->setDetails($information['details'] ?? '');
        $account->setDisplayName($information['displayName'] ?? '');
        $account->setIban($information['iban'] ?? '');
        $account->setLinkedAccounts($information['linkedAccounts'] ?? '');
        $account->setMsisdn($information['msisdn'] ?? '');
        $account->setName($information['name'] ?? '');
        $account->setOwnerAddressUnstructured($information['ownerAddressUnstructured'] ?? '');
        $account->setOwnerName($information['ownerName'] ?? '');
        $account->setProduct($information['product'] ?? '');
        $account->setResourceId($information['resourceId'] ?? '');
        $account->setStatus($information['status'] ?? '');
        $account->setUsage($information['usage'] ?? '');

        return $account;
    }

    private static function getBalanceDetails(Account $account): Account
    {
        Log::debug(sprintf('Now in %s(%s)', __METHOD__, $account->getIdentifier()));

        $url         = config('nordigen.url');
        $accessToken = TokenManager::getAccessToken();
        $request     = new GetAccountBalanceRequest($url, $accessToken, $account->getIdentifier());
        /** @var ArrayResponse $response */
        $response = $request->get();

        foreach ($response->data['balances'] as $array) {
            Log::debug(sprintf('Added "%s" balance "%s"', $array['balanceType'], $array['balanceAmount']['amount']));
            $account->addBalance(Balance::createFromArray($array));
        }
        return $account;
    }

}
