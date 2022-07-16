<?php
declare(strict_types=1);
/*
 * GetsRemoteData.php
 * Copyright (c) 2022 james@firefly-iii.org
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

namespace App\Support\Http;

use App\Services\Session\Constants;
use App\Services\Shared\Authentication\SecretManager;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;

/**
 * Trait GetsRemoteData
 */
trait GetsRemoteData
{

    /**
     * @return array[]
     * @throws ApiHttpException
     */
    protected function getFF3Accounts(): array
    {
        $accounts = [
            Constants::ASSET_ACCOUNTS => [],
            Constants::LIABILITIES    => [],
        ];

        // get list of asset accounts:
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
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
}
