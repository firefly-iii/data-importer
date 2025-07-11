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

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Exceptions\RateLimitException;
use App\Services\Nordigen\Model\Account;
use App\Services\Nordigen\Model\Balance;
use App\Services\Nordigen\Request\GetAccountBalanceRequest;
use App\Services\Nordigen\Request\GetAccountBasicRequest;
use App\Services\Nordigen\Request\GetAccountInformationRequest;
use App\Services\Nordigen\Response\ArrayResponse;
use App\Services\Nordigen\TokenManager;
use Illuminate\Support\Facades\Log;

/**
 * Class AccountInformationCollector
 *
 * Collects meta information and more on the given Account
 */
class AccountInformationCollector
{
    /**
     * @throws AgreementExpiredException
     */
    public static function collectInformation(Account $account, bool $overruleSettings = false): Account
    {
        Log::debug(sprintf('Now in %s', __METHOD__));

        // you know nothing, Jon Snow
        $detailedAccount = $account;

        if (config('nordigen.get_account_details') || $overruleSettings) {
            try {
                Log::debug('Get account details is ENABLED.');
                $detailedAccount = self::getAccountDetails($detailedAccount);
            } catch (ImporterErrorException $e) {
                Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
                // ignore error otherwise for now.
                $detailedAccount->setStatus('no-info');
                $detailedAccount->setName('Unknown account');
            }
        }


        if (config('nordigen.get_balance_details') || $overruleSettings) {
            Log::debug('Get account balance is ENABLED.');

            try {
                $detailedAccount = self::getBalanceDetails($detailedAccount);
            } catch (ImporterErrorException|ImporterHttpException $e) {
                Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
                // ignore error otherwise for now.
                $status = $detailedAccount->getStatus();
                if ('no-info' === $status) {
                    $detailedAccount->setStatus('nothing');
                }
                if ('no-info' !== $status) {
                    $detailedAccount->setStatus('no-balance');
                }
            }
        }

        if (!config('nordigen.get_account_details') && !$overruleSettings) {
            Log::debug('Get account details is DISABLED.');
        }

        if (!config('nordigen.get_balance_details') && !$overruleSettings) {
            Log::debug('Get account balance is DISABLED.');
        }

        // also collect some extra information, but don't use it right now.
        return self::getBasicDetails($detailedAccount);
    }

    /**
     * @throws AgreementExpiredException|ImporterErrorException
     */
    protected static function getAccountDetails(Account $account): Account
    {
        Log::debug(sprintf('Now in %s(%s)', __METHOD__, $account->getIdentifier()));

        $url          = config('nordigen.url');
        $accessToken  = TokenManager::getAccessToken();
        $request      = new GetAccountInformationRequest($url, $accessToken, $account->getIdentifier());
        $request->setTimeOut(config('importer.connection.timeout'));
        // @var ArrayResponse $response

        try {
            $response = $request->get();
        } catch (AgreementExpiredException $e) {
            // need to redirect user at some point.
            throw new AgreementExpiredException($e->getMessage(), 0, $e);
        } catch (ImporterErrorException|ImporterHttpException|RateLimitException $e) {
            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }

        if (!array_key_exists('account', $response->data)) {
            Log::error('Missing account array', $response->data);

            throw new ImporterErrorException('No account array received, perhaps rate limited.');
        }

        $information  = $response->data['account'];

        Log::debug('getAccountDetails: Collected information for account', $information);

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
        $account->setOwnerName($information['ownerName'] ?? '');
        $account->setProduct($information['product'] ?? '');
        $account->setResourceId($information['resourceId'] ?? '');
        $account->setStatus($information['status'] ?? '');
        $account->setUsage($information['usage'] ?? '');

        // set owner info (could be an array or string)
        $ownerAddress = [];
        if (array_key_exists('ownerAddressUnstructured', $information) && is_array($information['ownerAddressUnstructured'])) {
            $ownerAddress = $information['ownerAddressUnstructured'];
        }
        if (array_key_exists('ownerAddressUnstructured', $information) && is_string($information['ownerAddressUnstructured'])) {
            $ownerAddress = ['ownerAddressUnstructured' => $information['ownerAddressUnstructured']];
        }
        $account->setOwnerAddressUnstructured($ownerAddress);

        return $account;
    }

    private static function getBalanceDetails(Account $account): Account
    {
        Log::debug(sprintf('Now in %s(%s)', __METHOD__, $account->getIdentifier()));

        $url         = config('nordigen.url');
        $accessToken = TokenManager::getAccessToken();
        $request     = new GetAccountBalanceRequest($url, $accessToken, $account->getIdentifier());
        $request->setTimeOut(config('importer.connection.timeout'));

        // @var ArrayResponse $response
        try {
            $response = $request->get();
        } catch (AgreementExpiredException $e) {
            throw new AgreementExpiredException($e->getMessage(), 0, $e);
        } catch (ImporterErrorException|ImporterHttpException|RateLimitException $e) {
            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }
        if (array_key_exists('balances', $response->data)) {
            foreach ($response->data['balances'] as $array) {
                Log::debug(sprintf('Added "%s" balance "%s"', $array['balanceType'], $array['balanceAmount']['amount']));
                $account->addBalance(Balance::createFromArray($array));
            }
        }

        return $account;
    }

    private static function getBasicDetails(Account $account): Account
    {
        Log::debug(sprintf('Now in %s(%s)', __METHOD__, $account->getIdentifier()));

        $url         = config('nordigen.url');
        $accessToken = TokenManager::getAccessToken();
        $request     = new GetAccountBasicRequest($url, $accessToken, $account->getIdentifier());
        $request->setTimeOut(config('importer.connection.timeout'));

        /** @var ArrayResponse $response */
        $response    = $request->get();
        $array       = $response->data;
        Log::debug('Response for basic information request:', $array);

        // save IBAN if not already present
        if (array_key_exists('iban', $array) && '' !== $array['iban'] && '' === $account->getIban()) {
            Log::debug('Set new IBAN from basic details.');
            $account->setIban($array['iban']);
        }
        if (array_key_exists('owner_name', $array) && '' !== $array['owner_name'] && '' === $account->getOwnerName()) {
            Log::debug('Set new owner name from basic details.');
            $account->setOwnerName($array['owner_name']);
        }

        return $account;
    }
}
