<?php

declare(strict_types=1);
/*
 * CollectsAccounts.php
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

namespace App\Support\Http;

use App\Services\Shared\Authentication\SecretManager;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountsResponse;
use Illuminate\Support\Facades\Log;

trait CollectsAccounts
{
    protected function collectAllTargetAccounts(): array
    {
        Log::debug('Now in collectAllTargetAccounts()');

        try {
            $set1 = $this->collectAccounts('asset');
        } catch (ApiHttpException $e) {
            Log::error(sprintf('Could not collect asset accounts: %s', $e->getMessage()));
            $set1 = [];
        }

        try {
            $set2 = $this->collectAccounts('liabilities');
        } catch (ApiHttpException $e) {
            $set2 = [];
            Log::error(sprintf('Could not collect liability accounts: %s', $e->getMessage()));
        }
        $return = [];
        foreach ($set1 as $key => $value) {
            if (is_string($key) && !array_key_exists($key, $return)) {
                $return[$key] = $value;
            }
        }
        foreach ($set2 as $key => $value) {
            if (is_string($key) && !array_key_exists($key, $return)) {
                $return[$key] = $value;
            }
        }

        return $return;
    }

    protected function collectExpenseAccounts(): array
    {
        Log::debug('Now in collectExpenseAccounts()');

        try {
            $set1 = $this->collectAccounts('expense');
        } catch (ApiHttpException $e) {
            Log::error(sprintf('Could not collect expense accounts: %s', $e->getMessage()));
            $set1 = [];
        }

        $return = [];
        foreach ($set1 as $key => $value) {
            if (is_string($key) && !array_key_exists($key, $return)) {
                $return[$key] = $value;
            }
        }

        return $return;
    }

    protected function collectRevenueAccounts(): array
    {
        Log::debug('Now in collectExpenseAccounts()');

        try {
            $set1 = $this->collectAccounts('revenue');
        } catch (ApiHttpException $e) {
            Log::error(sprintf('Could not collect revenue accounts: %s', $e->getMessage()));
            $set1 = [];
        }

        $return = [];
        foreach ($set1 as $key => $value) {
            if (is_string($key) && !array_key_exists($key, $return)) {
                $return[$key] = $value;
            }
        }

        return $return;
    }

    /**
     * @throws ApiHttpException
     */
    private function collectAccounts(string $type): array
    {
        Log::debug(sprintf('Now in collectAccounts("%s")', $type));

        // send account list request to Firefly III.
        $token   = SecretManager::getAccessToken();
        $url     = SecretManager::getBaseUrl();
        $request = new GetAccountsRequest($url, $token);
        $request->setType($type);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));

        /** @var GetAccountsResponse $result */
        $result  = $request->get();
        Log::debug(sprintf('Found %d accounts of type "%s"', count($result), $type));
        $return  = [];

        /** @var Account $entry */
        foreach ($result as $entry) {
            Log::debug(sprintf('Processing account #%d ("%s") with type "%s"', $entry->id, $entry->name, $entry->type));
            $type          = $entry->type;
            $iban          = (string) $entry->iban;

            // For expense and revenue accounts, use account ID as key since they don't usually have IBANs
            if (in_array($type, ['expense', 'revenue'], true)) {
                $key          = sprintf('id_%d', $entry->id);
                Log::debug(sprintf('Collected %s account "%s" under key "%s"', $type, $entry->name, $key));
                $return[$key] = ['id' => $entry->id, 'type' => $entry->type, 'name' => $entry->name, 'number' => $entry->number];
                // #10546 drop the continue statement here.
            }

            // For asset/liability accounts, continue with IBAN-based logic
            if ('' === $iban) {
                Log::debug(sprintf('Account #%d ("%s") has no IBAN, skipping saving it under its IBAN.', $entry->id, $entry->name));
                continue;
            }
            $iban          = $this->filterSpaces($iban);
            $number        = sprintf('%s.', (string) $entry->number);
            if ('.' !== $number) {
                $number       = $this->filterSpaces((string) $entry->number);
                $key          = sprintf('nr_%s', $number);
                Log::debug(sprintf('Collected account nr "%s" (%s) under ID #%d', $key, $entry->type, $entry->id));
                $return[$key] = ['id' => $entry->id, 'type' => $entry->type, 'name' => $entry->name, 'number' => $entry->number];
            }
            // #10546 include expense and revenue accounts in the IBAN list, unless entry already exist.
            if(array_key_exists($iban, $return) && in_array($type, ['expense', 'revenue'], true)) {
                Log::debug(sprintf('Refuse to let expense/revenue account IBAN "%s" overrule existing IBAN entry.', $iban));
                continue; // skip this account, it is already in the list.
            }
            // #10546 allow asset and liability accounts to be added under their IBAN, overruling expense accounts if necessary.
            if(array_key_exists($iban, $return) && !in_array($type, ['expense', 'revenue'], true)) {
                Log::debug(sprintf('Allow %s account with IBAN "%s" to overrule existing IBAN entry.',$type, $iban));
                $return[$iban] = ['id' => $entry->id, 'type' => $entry->type, 'name' => $entry->name, 'number' => $entry->number];
            }
            // #10546 if the IBAN does not yet exist, add it of course.
            if(!array_key_exists($iban, $return)) {
                Log::debug(sprintf('Collected account IBAN "%s" (%s) under ID #%d', $iban, $entry->type, $entry->id));
                $return[$iban] = ['id' => $entry->id, 'type' => $entry->type, 'name' => $entry->name, 'number' => $entry->number];
            }

        }
        Log::debug(sprintf('Collected %d accounts of type "%s"', count($result), $type));

        return $return;
    }

    private function filterSpaces(string $iban): string
    {
        $search = [
            "\u{0001}", // start of heading
            "\u{0002}", // start of text
            "\u{0003}", // end of text
            "\u{0004}", // end of transmission
            "\u{0005}", // enquiry
            "\u{0006}", // ACK
            "\u{0007}", // BEL
            "\u{0008}", // backspace
            "\u{000E}", // shift out
            "\u{000F}", // shift in
            "\u{0010}", // data link escape
            "\u{0011}", // DC1
            "\u{0012}", // DC2
            "\u{0013}", // DC3
            "\u{0014}", // DC4
            "\u{0015}", // NAK
            "\u{0016}", // SYN
            "\u{0017}", // ETB
            "\u{0018}", // CAN
            "\u{0019}", // EM
            "\u{001A}", // SUB
            "\u{001B}", // escape
            "\u{001C}", // file separator
            "\u{001D}", // group separator
            "\u{001E}", // record separator
            "\u{001F}", // unit separator
            "\u{007F}", // DEL
            "\u{00A0}", // non-breaking space
            "\u{1680}", // ogham space mark
            "\u{180E}", // mongolian vowel separator
            "\u{2000}", // en quad
            "\u{2001}", // em quad
            "\u{2002}", // en space
            "\u{2003}", // em space
            "\u{2004}", // three-per-em space
            "\u{2005}", // four-per-em space
            "\u{2006}", // six-per-em space
            "\u{2007}", // figure space
            "\u{2008}", // punctuation space
            "\u{2009}", // thin space
            "\u{200A}", // hair space
            "\u{200B}", // zero width space
            "\u{202F}", // narrow no-break space
            "\u{3000}", // ideographic space
            "\u{FEFF}", // zero width no -break space
            "\x20", // plain old normal space
        ];

        return str_replace($search, '', $iban);
    }
}
