<?php
declare(strict_types=1);
/**
 * GetAccounts.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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

namespace App\Services\CSV\Mapper;

use App\Exceptions\ImportException;
use App\Support\Token;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountsResponse;
use Log;

/**
 * Trait GetAccounts
 */
trait GetAccounts
{

    /**
     * Returns a combined list of asset accounts and all liability accounts.
     *
     * @return array
     * @throws ImportException
     */
    protected function getAllAccounts(): array
    {
        // get list of asset accounts:
        $accounts = [];
        $url      = Token::getURL();
        $token    = Token::getAccessToken();
        $request  = new GetAccountsRequest($url, $token);

        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));
        $request->setType(GetAccountsRequest::ALL);

        /** @var GetAccountsResponse $response */
        try {
            $response = $request->get();
        } catch (ApiHttpException $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            throw new ImportException(sprintf('Could not download accounts: %s', $e->getMessage()));
        }

        if ($response instanceof GetAccountsResponse) {
            $accounts = $this->toArray($response);
        }

        if (!$response instanceof GetAccountsResponse) {
            throw new ImportException('Could not get list of ALL accounts.');
        }

        return array_merge($accounts);
    }

    /**
     * @param GetAccountsResponse $list
     *
     * @return array
     */
    private function toArray(GetAccountsResponse $list): array
    {
        $return = [];
        foreach ($list as $account) {
            $return[] = $account;
        }

        return $return;
    }

    /**
     * Returns a combined list of asset accounts and all liability accounts.
     *
     * @return array
     * @throws ImportException
     */
    protected function getAssetAccounts(): array
    {
        // get list of asset accounts:
        $accounts    = [];
        $liabilities = [];
        $url         = Token::getURL();
        $token       = Token::getAccessToken();
        $request     = new GetAccountsRequest($url, $token);

        $request->setType(GetAccountsRequest::ASSET);
        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));

        /** @var GetAccountsResponse $response */
        try {
            $response = $request->get();
        } catch (ApiHttpException $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            throw new ImportException(sprintf('Could not download asset accounts: %s', $e->getMessage()));
        }

        if ($response instanceof GetAccountsResponse) {
            $accounts = $this->toArray($response);
        }

        if (!$response instanceof GetAccountsResponse) {
            throw new ImportException('Could not get list of asset accounts.');
        }

        $request = new GetAccountsRequest($url, $token);

        $request->setType(GetAccountsRequest::LIABILITIES);
        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));

        /** @var GetAccountsResponse $response */
        try {
            $response = $request->get();
        } catch (ApiHttpException $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            throw new ImportException(sprintf('Could not download liability accounts: %s', $e->getMessage()));
        }

        if ($response instanceof GetAccountsResponse) {
            $liabilities = $this->toArray($response);
        }

        if (!$response instanceof GetAccountsResponse) {
            throw new ImportException('Could not get list of asset accounts.');
        }

        return array_merge($accounts, $liabilities);
    }

    /**
     * Merge all arrays into <select> ready list.
     *
     * @param array $accounts
     *
     * @return array
     */
    protected function mergeAll(array $accounts): array
    {
        $invalidTypes = ['initial-balance', 'reconciliation'];
        $result       = [];
        /** @var Account $account */
        foreach ($accounts as $account) {
            $name = $account->name;

            // remove some types:
            if (in_array($account->type, $invalidTypes, true)) {
                continue;
            }

            if (null !== $account->iban) {
                $name = sprintf('%s (%s)', $account->name, $account->iban);
            }

            // add optgroup to result:
            $group                        = trans(sprintf('import.account_types_%s', $account->type));
            $result[$group]               = $result[$group] ?? [];
            $result[$group][$account->id] = $name;
        }
        foreach ($result as $group => $accounts) {
            asort($accounts, SORT_STRING);
            $result[$group] = $accounts;
        }

        return $result;
    }

    /**
     * Merge all arrays into <select> ready list.
     *
     * @param array $accounts
     *
     * @return array
     */
    protected function mergeWithIBAN(array $accounts): array
    {
        $result       = [];
        $invalidTypes = ['initial-balance', 'reconciliation'];
        /** @var Account $account */
        foreach ($accounts as $account) {

            // remove some types:
            if (in_array($account->type, $invalidTypes, true)) {
                continue;
            }

            // only merge if IBAN is not null.
            if (null !== $account->iban) {


                $name = sprintf('%s (%s)', $account->name, $account->iban);
                // add optgroup to result:
                $group                        = trans(sprintf('import.account_types_%s', $account->type));
                $result[$group]               = $result[$group] ?? [];
                $result[$group][$account->id] = $name;
            }
        }

        foreach ($result as $group => $accounts) {
            asort($accounts, SORT_STRING);
            $result[$group] = $accounts;
        }

        return $result;
    }
}
