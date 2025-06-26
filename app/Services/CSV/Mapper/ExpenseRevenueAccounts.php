<?php

/*
 * ExpenseRevenueAccounts.php
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

namespace App\Services\CSV\Mapper;

use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Authentication\SecretManager;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountsResponse;
use Illuminate\Support\Facades\Log;

/**
 * Class ExpenseRevenueAccounts
 */
class ExpenseRevenueAccounts implements MapperInterface
{
    /**
     * Get map of expense and revenue accounts.
     *
     * @throws ImporterErrorException
     */
    public function getMap(): array
    {
        $accounts = $this->getExpenseRevenueAccounts();

        return $this->mergeExpenseRevenue($accounts);
    }

    /**
     * Get expense and revenue accounts from Firefly III API.
     */
    protected function getExpenseRevenueAccounts(): array
    {
        Log::debug('getExpenseRevenueAccounts: Fetching expense and revenue accounts.');

        $url                    = SecretManager::getBaseUrl();
        $token                  = SecretManager::getAccessToken();

        // Fetch all accounts since API doesn't have separate expense/revenue endpoints
        $request                = new GetAccountsRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setType(GetAccountsRequest::ALL);

        try {
            $response = $request->get();
        } catch (ApiHttpException $e) {
            Log::error($e->getMessage());

            throw new ImporterErrorException(sprintf('Could not download accounts: %s', $e->getMessage()));
        }

        if (!$response instanceof GetAccountsResponse) {
            throw new ImporterErrorException('Could not get list of accounts.');
        }

        $allAccounts            = $this->toArray($response);

        // Filter for expense and revenue accounts only
        $expenseRevenueAccounts = array_filter($allAccounts, fn(Account $account) => in_array($account->type, ['expense', 'revenue'], true));

        Log::debug(sprintf('getExpenseRevenueAccounts: Found %d expense/revenue accounts', count($expenseRevenueAccounts)));

        return $expenseRevenueAccounts;
    }

    /**
     * Convert response to array of Account objects.
     */
    protected function toArray(GetAccountsResponse $response): array
    {
        $accounts = [];

        /** @var Account $account */
        foreach ($response as $account) {
            $accounts[] = $account;
        }

        return $accounts;
    }

    /**
     * Merge expense and revenue accounts into select-ready list.
     */
    protected function mergeExpenseRevenue(array $accounts): array
    {
        $result       = [];
        $invalidTypes = ['initial-balance', 'reconciliation'];

        /** @var Account $account */
        foreach ($accounts as $account) {
            // Skip invalid types
            if (in_array($account->type, $invalidTypes, true)) {
                continue;
            }

            // Only include expense and revenue accounts
            if (!in_array($account->type, ['expense', 'revenue'], true)) {
                continue;
            }

            $name                         = $account->name;

            // Add optgroup to result
            $group                        = trans(sprintf('import.account_types_%s', $account->type));
            $result[$group] ??= [];
            $result[$group][$account->id] = $name;
        }

        // Sort each group
        foreach ($result as $group => $accounts) {
            asort($accounts, SORT_STRING);
            $result[$group] = $accounts;
        }

        // Create new account functionality temporarily removed for stock compatibility

        return $result;
    }
}
