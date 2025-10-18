<?php

/*
 * ImportServiceAccount.php
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

namespace App\Services\Shared\Model;

use App\Services\CSV\Converter\Iban as IbanConverter;
use App\Services\LunchFlow\Model\Account as LunchFlowAccount;
use App\Services\Nordigen\Model\Account as NordigenAccount;
use App\Services\Nordigen\Model\Balance;
use App\Services\Spectre\Model\Account as SpectreAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ImportServiceAccount
{
    public string $bban;
    public string $currencyCode;
    public array  $extra;
    public string $iban;
    public string $id;
    public string $name;
    public string $status;

    /**
     * @param array $accounts
     * @return array<ImportServiceAccount>
     */
    public static function convertNordigenArray(array $accounts): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $return = [];

        /** @var NordigenAccount $account */
        foreach ($accounts as $account) {
            $iban = $account->getIban();
            if ('' !== $iban && false === IbanConverter::isValidIban($iban)) {
                Log::debug(sprintf('IBAN "%s" is invalid so it will be ignored.', $iban));
                $iban = '';
            }

            $current = self::fromArray(
                [
                    'id'            => $account->getIdentifier(),
                    'name'          => $account->getFullName(),
                    'currency_code' => $account->getCurrency(),
                    'iban'          => $iban,
                    'bban'          => $account->getBban(),
                    'status'        => '',
                    'extra'         => [
                        'Name'         => $account->getName(),
                        'Display name' => $account->getDisplayName(),
                        'Owner name'   => $account->getOwnerName(),
                        'Currency'     => $account->getCurrency(),
                        'IBAN'         => $iban,
                        'BBAN'         => $account->getBban(),
                    ],
                ]
            );

            /** @var Balance $balance */
            foreach ($account->getBalances() as $balance) {
                $key                  = sprintf('Balance (%s) (%s)', $balance->type, $balance->currency);
                $current->extra[$key] = $balance->amount;
            }
            $return[] = $current;
        }

        return $return;
    }

    public static function convertSimpleFINArray(array $accounts): array
    {
        $return = [];

        foreach ($accounts as $account) {
            $timestamp = (int) $account['balance-date'] ?? 0;
            $dateString = '';
            if($timestamp > 100){
                $carbon = Carbon::createFromTimestamp($timestamp);
                $dateString = $carbon->format('Y-m-d H:i:s');
            }
            $current = self::fromArray(
                [
                    'id'            => $account['id'], // Expected by component for form elements, and by getMappedTo (as 'identifier')
                    'name'          => $account['name'], // Expected by getMappedTo, display in component
                    'currency_code' => $account['currency'] ?? null, // SimpleFIN currency field
                    'iban'          => null,
                    'bban'          => '',
                    'status'        => 'active', // Expected by view for status checks
                    'extra'         => [
                        'Balance'      => $account['balance'] ?? null, // SimpleFIN balance (numeric string)
                        'Balance date' => $dateString, // SimpleFIN balance timestamp
                        'Organization' => $account['org']['name'] ?? null, // SimpleFIN organization data
                    ]
                ]
            );
            foreach ($account['extra'] ?? [] as $key => $value) {
                if(!array_key_exists($key, $current->extra)){
                    $current->extra[$key] = $value;
                }
            }
            $return[] = $current;
//            $return[] = ['import_account'       => $importAccountRepresentation, // The DTO-like object for the component
//                         'mapped_to'            => $this->getMappedTo((object)['identifier' => $importAccountRepresentation->id, 'name' => $importAccountRepresentation->name], $fireflyAccounts), // getMappedTo needs 'identifier'
//                         'type'                 => 'source', // Indicates it's an account from the import source
//                         'firefly_iii_accounts' => $fireflyAccounts, // Required by x-importer-account component
//            ];
        }

        return $return;
    }

    /**
     * @return $this
     */
    public static function fromArray(array $array): self
    {
        Log::debug('Create generic account from', $array);
        $iban = (string)($array['iban'] ?? '');
        if ('' !== $iban && false === IbanConverter::isValidIban($iban)) {
            Log::debug(sprintf('IBAN "%s" is invalid so it will be ignored.', $iban));
            $iban = '';
        }
        $account               = new self();
        $account->id           = $array['id'];
        $account->name         = $array['name'];
        $account->iban         = $iban;
        $account->bban         = $array['bban'];
        $account->currencyCode = $array['currency_code'];
        $account->status       = $array['status'];
        $account->extra        = $array['extra'];

        return $account;
    }

    public static function convertSpectreArray(array $spectre): array
    {
        $return = [];

        /** @var SpectreAccount $account */
        foreach ($spectre as $account) {
            $iban = (string)$account->iban;
            if ('' !== $iban && false === IbanConverter::isValidIban($iban)) {
                Log::debug(sprintf('IBAN "%s" is invalid so it will be ignored.', $iban));
                $iban = '';
            }
            $return[] = self::fromArray(
                [
                    'id'            => $account->id,
                    'name'          => $account->name,
                    'currency_code' => $account->currencyCode,
                    'iban'          => $iban,
                    'bban'          => $account->accountNumber,
                    'status'        => $account->status,
                    'extra'         => [
                        'Currency' => $account->currencyCode,
                        'IBAN'     => $iban,
                        'BBAN'     => $account->accountNumber,
                    ],
                ]
            );
        }

        return $return;
    }

    public static function convertLunchFlowArray(array $lunchFlow): array
    {
        $return = [];

        /** @var LunchFlowAccount $account */
        foreach ($lunchFlow as $account) {
            $return[] = self::fromArray(
                [
                    'id'            => (string)$account->id,
                    'name'          => $account->name,
                    'currency_code' => (string)$account->currency,
                    'iban'          => '',
                    'bban'          => '',
                    'status'        => $account->status,
                    'extra'         => [
                        'Currency' => (string)$account->currency,
                        'IBAN'     => '',
                        'BBAN'     => '',
                    ],
                ]
            );
        }

        return $return;
    }
}
