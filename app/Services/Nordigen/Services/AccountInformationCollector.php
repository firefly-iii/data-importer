<?php
/*
 * AccountInformationCollector.php
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


namespace App\Services\Nordigen\Services;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
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
        $detailedAccount = $account;
        try {
            $detailedAccount = self::getAccountDetails($account);
        } catch (ImporterHttpException | ImporterErrorException $e) {
            Log::error($e->getMessage());
            // ignore error otherwise for now.
            $detailedAccount->setStatus('no-info');
            $detailedAccount->setName('Unknown account');
        }
        $balanceAccount = $detailedAccount;

        try {
            $balanceAccount = self::getBalanceDetails($account);
        } catch (ImporterHttpException | ImporterErrorException $e) {
            Log::error($e->getMessage());
            // ignore error otherwise for now.
            $status = $balanceAccount->getStatus();
            if ('no-info' === $status) {
                $balanceAccount->setStatus('nothing');
            }
            if ('no-info' !== $status) {
                $balanceAccount->setStatus('no-balance');
            }
        }
        // overrule settings to test layout:
//        $balanceAccount->setIban('');
//        $balanceAccount->setName('');
//        $balanceAccount->setDisplayName('');
//        $balanceAccount->setOwnerName('');
//        $balanceAccount->setStatus('no-info');

        return $balanceAccount;
    }

    /**
     * @param Account $account
     * @return Account
     * @throws ImporterErrorException
     * @throws ImporterHttpException
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

    /**
     * @param Account $account
     * @return Account
     * @throws ImporterErrorException
     * @throws ImporterHttpException
     */
    private static function getBalanceDetails(Account $account): Account
    {
        Log::debug(sprintf(sprintf('Now in %s(%s)', __METHOD__, $account->getIdentifier())));

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
