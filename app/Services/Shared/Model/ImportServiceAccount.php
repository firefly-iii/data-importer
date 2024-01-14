<?php

/*
 * ImportServiceAccount.php
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

namespace App\Services\Shared\Model;

use App\Services\CSV\Converter\Iban as IbanConverter;
use App\Services\Nordigen\Model\Account as NordigenAccount;
use App\Services\Nordigen\Model\Balance;
use App\Services\Spectre\Model\Account as SpectreAccount;

class ImportServiceAccount
{
    public string $bban;
    public string $currencyCode;
    public string $iban;
    public string $id;
    public string $name;
    public string $status;
    public array  $extra;

    public static function convertNordigenArray(array $accounts): array
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $return = [];

        /** @var NordigenAccount $account */
        foreach ($accounts as $account) {
            $iban     = $account->getIban();
            if ('' !== $iban && false === IbanConverter::isValidIban($iban)) {
                app('log')->debug(sprintf('IBAN "%s" is invalid so it will be ignored.', $iban));
                $iban = '';
            }

            $current  = self::fromArray(
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

    public static function convertSpectreArray(array $spectre): array
    {
        $return = [];

        /** @var SpectreAccount $account */
        foreach ($spectre as $account) {
            $iban     = (string)$account->iban;
            if ('' !== $iban && false === IbanConverter::isValidIban($iban)) {
                app('log')->debug(sprintf('IBAN "%s" is invalid so it will be ignored.', $iban));
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

    /**
     * @return $this
     */
    public static function fromArray(array $array): self
    {
        app('log')->debug('Create generic account from', $array);
        $iban                  = (string)($array['iban'] ?? '');
        if ('' !== $iban && false === IbanConverter::isValidIban($iban)) {
            app('log')->debug(sprintf('IBAN "%s" is invalid so it will be ignored.', $iban));
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
}
