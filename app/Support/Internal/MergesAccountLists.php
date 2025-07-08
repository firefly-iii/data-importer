<?php

/*
 * MergesAccountLists.php
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

declare(strict_types=1);

namespace App\Support\Internal;

use App\Services\Session\Constants;
use App\Services\Shared\Model\ImportServiceAccount;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use Illuminate\Support\Facades\Log;

trait MergesAccountLists
{
    protected function mergeNordigenAccountLists(array $nordigen, array $fireflyIII): array
    {
        Log::debug('Now merging GoCardless account lists.');
        $generic = ImportServiceAccount::convertNordigenArray($nordigen);

        return $this->mergeGenericAccountList($generic, $fireflyIII);
    }

    private function mergeGenericAccountList(array $generic, array $fireflyIII): array
    {
        $return = [];

        /** @var ImportServiceAccount $account */
        foreach ($generic as $account) {
            Log::debug(sprintf('Working on generic account name: "%s": id:"%s" (iban:"%s", number:"%s")', $account->name, $account->id, $account->iban, $account->bban));

            $iban             = $account->iban;
            $number           = $account->bban;
            $currency         = $account->currencyCode;
            $entry            = [
                'import_account'       => $account,
                'firefly_iii_accounts' => [
                    Constants::ASSET_ACCOUNTS => [],
                    Constants::LIABILITIES    => [],
                ],
            ];

            // Always show all accounts, but sort matches to the top
            $filteredByNumber = $this->filterByAccountNumber($fireflyIII, $iban, $number);

            foreach ([Constants::ASSET_ACCOUNTS, Constants::LIABILITIES] as $key) {
                $matching                            = $filteredByNumber[$key];
                $all                                 = $fireflyIII[$key];

                // Remove matching from all to avoid duplicates
                $nonMatching                         = array_udiff($all, $matching, function ($a, $b) {
                    return $a->id <=> $b->id;
                });

                // Concatenate: matches first, then the rest
                $entry['firefly_iii_accounts'][$key] = array_merge($matching, $nonMatching);
            }

            $return[]         = $entry;
        }

        return $return;
    }

    protected function filterByAccountNumber(array $fireflyIII, string $iban, string $number): array
    {
        Log::debug(sprintf('Now filtering Firefly III accounts by IBAN "%s" or number "%s".', $iban, $number));
        // FIXME this check should also check the number of the account.
        if ('' === $iban) {
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
                if ($iban === $account->iban || $number === $account->number || $iban === $account->number || $number === $account->iban) {
                    $result[$key][] = $account;
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

    protected function mergeSpectreAccountLists(array $spectre, array $fireflyIII): array
    {
        Log::debug('Now merging Spectre account lists.');
        $generic = ImportServiceAccount::convertSpectreArray($spectre);

        return $this->mergeGenericAccountList($generic, $fireflyIII);
    }
}
