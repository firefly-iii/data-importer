<?php
/**
 * Account.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III Spectre importer
 * (https://github.com/firefly-iii/spectre-importer).
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

namespace App\Services\Spectre\Model;

/**
 * Class Account
 */
class Account
{
    public string $accountName;
    public string $accountNumber;
    public float  $balance;
    public string $cardType;
    public string $clientName;
    public string $connectionId;
    public string $currencyCode;
    public string $iban;
    public string $id;
    public bool   $matched;
    public string $name;
    public string $nature;
    public string $sortCode;
    public string $status;
    public string $swift;

    /**
     * Account constructor.
     */
    private function __construct()
    {
    }

    /**
     * @param array $data
     *
     * @return static
     */
    public static function fromArray(array $data): self
    {
        $model                = new self;
        $model->matched       = false;
        $model->id            = (string)$data['id'];
        $model->accountName   = $data['extra']['account_name'] ?? '';
        $model->accountNumber = $data['extra']['account_number'] ?? '';
        $model->balance       = $data['balance'] ?? 0;
        $model->cardType      = $data['extra']['card_type'] ?? '';
        $model->clientName    = $data['extra']['client_name'] ?? '';
        $model->connectionId  = (string)$data['connection_id'];
        $model->currencyCode  = $data['currency_code'];
        $model->iban          = $data['extra']['iban'] ?? '';
        $model->name          = $data['name'];
        $model->nature        = $data['nature'];
        $model->sortCode      = $data['extra']['sort_code'] ?? '';
        $model->status        = $data['extra']['status'] ?? 'unknown';
        $model->swift         = $data['extra']['swift'] ?? '';

        return $model;
    }
}
