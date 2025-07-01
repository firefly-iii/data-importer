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

            $iban                          = $account->iban;
            $number                        = $account->bban;
            $currency                      = $account->currencyCode;
            $entry                         = [
                'import_account' => $account,
                'firefly_iii_accounts' => [
                    Constants::ASSET_ACCOUNTS => [],
                    Constants::LIABILITIES    => [],
                ],
            ];

            $filteredByNumber              = $this->filterByAccountNumber($fireflyIII, $iban, $number);
            $filteredByCurrency            = $this->filterByCurrency($fireflyIII, $currency);
            Log::debug('Filtered by number', $filteredByNumber);
            Log::debug('Filtered by currency', $filteredByCurrency);
            $count = 0;
            foreach([Constants::ASSET_ACCOUNTS, Constants::LIABILITIES] as $key) {
                if (1 === count($filteredByNumber[$key])) {
                    Log::debug(sprintf('Generic account ("%s", "%s") has a single FF3 %s counter part (#%d, "%s")', $iban, $number, $key, $filteredByNumber[$key][0]->id, $filteredByNumber[$key][0]->name));
                    $entry['firefly_iii_accounts'][$key] = array_unique(array_merge(
                        $filteredByNumber[$key],
                        $filteredByCurrency[$key]), SORT_REGULAR);
                    $return[]                      = $entry;
                    $count++;
                    continue 2;
                }
            }

            Log::debug(sprintf('Found %d FF3 accounts with the same IBAN or number ("%s")', $count, $iban));
            unset($count);

            foreach([Constants::ASSET_ACCOUNTS, Constants::LIABILITIES] as $key) {
                if (count($filteredByCurrency[$key]) > 0) {
                    Log::debug(sprintf('Generic account ("%s") has %d Firefly III %s counter part(s) with the same currency %s.', $account->name, $key, count($filteredByCurrency), $currency));
                    $entry['firefly_iii_accounts'][$key] = $filteredByCurrency[$key];
                    $return[]                      = $entry;
                    continue 2;
                }
            }
            Log::debug('No special filtering on the Firefly III account list.');
            // remove array_merge because SimpleFIN does not do this so it broke all the other importer routines.
            $entry['firefly_iii_accounts'] = $fireflyIII;
            $return[]                      = $entry;
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

        foreach($fireflyIII as $key => $accounts) {
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

        foreach($fireflyIII as $key => $accounts) {
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
