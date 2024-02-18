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

trait MergesAccountLists
{
    protected function filterByAccountNumber(array $firefly, string $iban, string $number): array
    {
        // FIXME this check should also check the number of the account.
        if ('' === $iban) {
            return [];
        }
        $result = [];
        // TODO check if this the correct merge type.
        $all    = array_merge($firefly[Constants::ASSET_ACCOUNTS] ?? [], $firefly[Constants::LIABILITIES] ?? []);

        /** @var Account $account */
        foreach ($all as $account) {
            if ($iban === $account->iban || $number === $account->number || $iban === $account->number || $number === $account->iban) {
                $result[] = $account;
            }
        }

        return $result;
    }

    protected function filterByCurrency(array $fireflyIII, string $currency): array
    {
        if ('' === $currency) {
            return [];
        }
        $result = [];
        $all    = array_merge($fireflyIII[Constants::ASSET_ACCOUNTS] ?? [], $fireflyIII[Constants::LIABILITIES] ?? []);

        /** @var Account $account */
        foreach ($all as $account) {
            if ($currency === $account->currencyCode) {
                $result[] = $account;
            }
        }

        return $result;
    }

    protected function mergeNordigenAccountLists(array $nordigen, array $fireflyIII): array
    {
        app('log')->debug('Now merging Nordigen account lists.');
        $generic = ImportServiceAccount::convertNordigenArray($nordigen);

        return $this->mergeGenericAccountList($generic, $fireflyIII);
    }

    protected function mergeSpectreAccountLists(array $spectre, array $fireflyIII): array
    {
        app('log')->debug('Now merging Spectre account lists.');
        $generic = ImportServiceAccount::convertSpectreArray($spectre);

        return $this->mergeGenericAccountList($generic, $fireflyIII);
    }

    private function mergeGenericAccountList(array $generic, array $fireflyIII): array
    {
        $return = [];

        /** @var ImportServiceAccount $account */
        foreach ($generic as $account) {
            app('log')->debug(sprintf('Working on generic account "%s": "%s" ("%s", "%s")', $account->name, $account->id, $account->iban, $account->bban));

            $iban                          = $account->iban;
            $number                        = $account->bban;
            $currency                      = $account->currencyCode;
            $entry                         = [
                'import_account' => $account,
            ];

            $filteredByNumber              = $this->filterByAccountNumber($fireflyIII, $iban, $number);
            $filteredByCurrency            = $this->filterByCurrency($fireflyIII, $currency);

            if (1 === count($filteredByNumber)) {
                app('log')->debug(sprintf('Generic account ("%s", "%s") has a single FF3 counter part (#%d, "%s")', $iban, $number, $filteredByNumber[0]->id, $filteredByNumber[0]->name));
                $entry['firefly_iii_accounts'] = array_unique(array_merge($filteredByNumber, $filteredByCurrency), SORT_REGULAR);
                $return[]                      = $entry;

                continue;
            }
            app('log')->debug(sprintf('Found %d FF3 accounts with the same IBAN or number ("%s")', count($filteredByNumber), $iban));

            if (count($filteredByCurrency) > 0) {
                app('log')->debug(sprintf('Generic account ("%s") has %d Firefly III counter part(s) with the same currency %s.', $account->name, count($filteredByCurrency), $currency));
                $entry['firefly_iii_accounts'] = $filteredByCurrency;
                $return[]                      = $entry;

                continue;
            }
            app('log')->debug('No special filtering on the Firefly III account list.');
            $entry['firefly_iii_accounts'] = array_merge($fireflyIII[Constants::ASSET_ACCOUNTS], $fireflyIII[Constants::LIABILITIES]);
            $return[]                      = $entry;
        }

        return $return;
    }
}
