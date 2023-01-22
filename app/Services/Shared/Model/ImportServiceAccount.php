<?php

declare(strict_types=1);
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

namespace App\Services\Shared\Model;

use App\Services\Nordigen\Model\Account as NordigenAccount;
use App\Services\Spectre\Model\Account as SpectreAccount;

class ImportServiceAccount
{
    public string $id;
    public string $name;
    public string $iban;
    public string $bban;
    public string $currencyCode;
    public string $status;

    /**
     * @param array $accounts
     *
     * @return array
     */
    public static function convertNordigenArray(array $accounts): array
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $return = [];
        /** @var NordigenAccount $account */
        foreach ($accounts as $account) {
            $return[] = self::fromArray(
                [
                    'id'            => $account->getIdentifier(),
                    'name'          => $account->getName(),
                    'iban'          => $account->getIban(),
                    'bban'          => $account->getBban(),
                    'currency_code' => $account->getCurrency(),
                    'status'        => '',
                ]
            );
        }

        return $return;
    }

    /**
     * @param array $array
     *
     * @return $this
     */
    public static function fromArray(array $array): self
    {
        app('log')->debug('Create generic account from', $array);
        $account               = new self();
        $account->id           = $array['id'];
        $account->name         = $array['name'];
        $account->iban         = $array['iban'];
        $account->bban         = $array['bban'];
        $account->currencyCode = $array['currency_code'];
        $account->status       = $array['status'];

        return $account;
    }

    /**
     * @param array $spectre
     *
     * @return array
     */
    public static function convertSpectreArray(array $spectre): array
    {
        $return = [];
        /** @var SpectreAccount $account */
        foreach ($spectre as $account) {
            $return[] = self::fromArray(
                [
                    'id'            => $account->id,
                    'name'          => $account->name,
                    'iban'          => $account->iban,
                    'bban'          => $account->accountNumber,
                    'currency_code' => $account->currencyCode,
                    'status'        => $account->status,
                ]
            );
        }

        return $return;
    }
}
