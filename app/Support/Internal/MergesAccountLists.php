<?php

/*
 * MergesAccountLists.php
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

use App\Services\Session\Constants;
use App\Services\Shared\Model\ImportServiceAccount;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use Illuminate\Support\Facades\Log;

trait MergesAccountLists
{
    private function mergeGenericAccountList(array $generic, array $fireflyIII): array
    {
        Log::debug('Now in mergeGenericAccountList');
        $return = [];

        /** @var ImportServiceAccount $account */
        foreach ($generic as $account) {
            Log::debug(sprintf('Working on generic account name: "%s": id:"%s" (iban:"%s", number:"%s")', $account->name, $account->id, $account->iban, $account->bban));

            $entry    = [
                'import_account'       => $account,
                'firefly_iii_accounts' => [
                    Constants::ASSET_ACCOUNTS => [],
                    Constants::LIABILITIES    => [],
                ],
            ];

            // Always show all accounts, but sort matches to the top
            $filtered = $this->filterByAccountInfo($fireflyIII, $account);
            foreach ([Constants::ASSET_ACCOUNTS, Constants::LIABILITIES] as $key) {
                $matching = $filtered[$key];
                $all      = $fireflyIII[$key];

                Log::debug(sprintf('There are %d accounts in $fireflyIII[%s], and %d (is) are matching', count($fireflyIII[$key]), $key, count($matching)));

                // Remove matching from all to avoid duplicates
                $nonMatching = array_udiff($all, $matching, fn(Account $a, Account $b) => $a->id <=> $b->id);

                // Concatenate: matches first, then the rest
                $entry['firefly_iii_accounts'][$key] = array_merge($matching, $nonMatching);
            }
            $return[] = $entry;
        }
        Log::debug('done with mergeGenericAccountList');

        return $return;
    }

    private function filterByAccountInfo(array $applicationAccounts, ImportServiceAccount $importServiceAccount): array
    {
        Log::debug(sprintf('Now filtering Firefly III accounts by IBAN "%s", number "%s" or name "%s" (in that order).', $importServiceAccount->iban, $importServiceAccount->bban, $importServiceAccount->name));
        $result = [
            Constants::ASSET_ACCOUNTS => [],
            Constants::LIABILITIES    => [],
        ];

        foreach ($applicationAccounts as $key => $set) {
            /** @var Account $applicationAccount */
            foreach ($set as $applicationAccount) {
                // match on IBAN!
                if ('' !== $importServiceAccount->iban && $importServiceAccount->iban === $applicationAccount->iban) {
                    $applicationAccount->match = true;
                    $result[$key][] = $applicationAccount;
                    continue;
                }
                // match on IBAN, but based on the "number" field.
                if ('' !== $importServiceAccount->iban && $importServiceAccount->iban === $applicationAccount->accountNumber) {
                    $applicationAccount->match = true;
                    $result[$key][] = $applicationAccount;
                    continue;
                }
                // match on account number
                if ('' !== $importServiceAccount->bban && $importServiceAccount->bban === $applicationAccount->accountNumber) {
                    $applicationAccount->match = true;
                    $result[$key][] = $applicationAccount;
                    continue;
                }

                // match on name.
                if('' !== $importServiceAccount->name && $importServiceAccount->name === $applicationAccount->name) {
                    $applicationAccount->match = true;
                    $result[$key][] = $applicationAccount;
                    continue;
                }
            }
        }
        return $result;
    }

    protected function filterByCurrency(array $fireflyIII, string $currency): array
    {
        if ('' === $currency) {
            return [
                Constants::ASSET_ACCOUNTS => [],
                Constants::LIABILITIES    => [],
            ];
        }
        $result = [
            Constants::ASSET_ACCOUNTS => [],
            Constants::LIABILITIES    => [],
        ];

        foreach ($fireflyIII as $key => $accounts) {
            foreach ($accounts as $account) {
                if ($currency === $account->currencyCode) {
                    $result[$key][] = $account;
                }
            }
        }

        return $result;
    }

    protected function mergeSpectreAccountLists(array $spectre, array $applicationAccounts): array
    {
        Log::debug('Now merging Spectre account lists.');
        $generic = ImportServiceAccount::convertSpectreArray($spectre);

        return $this->mergeGenericAccountList($generic, $applicationAccounts);
    }

    protected function mergeLunchFlowAccountLists(array $lunchFlow, array $applicationAccounts): array
    {
        Log::debug('Now merging Lunch Flow account lists.');
        $generic = ImportServiceAccount::convertLunchFlowArray($lunchFlow);

        return $this->mergeGenericAccountList($generic, $applicationAccounts);
    }
}
