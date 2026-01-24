<?php

/*
 * Account.php
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

namespace App\Services\EnableBanking\Model;

use Illuminate\Support\Facades\Log;

/**
 * Class Account
 */
class Account
{
    private string $uid = '';
    private string $iban = '';
    private string $bban = '';
    private string $currency = '';
    private string $ownerName = '';
    private string $displayName = '';
    private string $product = '';
    private string $accountType = '';
    private array $balances = [];

    public function __construct()
    {
    }

    public static function fromArray(array $array): self
    {
        $account = new self();
        $account->uid = $array['uid'] ?? $array['account_uid'] ?? '';
        $account->iban = $array['account_id']['iban'] ?? $array['iban'] ?? '';
        $account->bban = $array['account_id']['bban'] ?? $array['bban'] ?? '';
        $account->currency = $array['currency'] ?? '';
        $account->ownerName = $array['owner_name'] ?? $array['account_holder_name'] ?? '';
        $account->displayName = $array['display_name'] ?? $array['name'] ?? '';
        $account->product = $array['product'] ?? '';
        $account->accountType = $array['account_type'] ?? '';

        return $account;
    }

    public static function fromLocalArray(array $array): self
    {
        $account = new self();
        $account->uid = $array['uid'] ?? '';
        $account->iban = $array['iban'] ?? '';
        $account->bban = $array['bban'] ?? '';
        $account->currency = $array['currency'] ?? '';
        $account->ownerName = $array['owner_name'] ?? '';
        $account->displayName = $array['display_name'] ?? '';
        $account->product = $array['product'] ?? '';
        $account->accountType = $array['account_type'] ?? '';
        $account->balances = $array['balances'] ?? [];

        return $account;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function setUid(string $uid): void
    {
        $this->uid = $uid;
    }

    public function getIban(): string
    {
        return $this->iban;
    }

    public function setIban(string $iban): void
    {
        $this->iban = $iban;
    }

    public function getBban(): string
    {
        return $this->bban;
    }

    public function setBban(string $bban): void
    {
        $this->bban = $bban;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getOwnerName(): string
    {
        return $this->ownerName;
    }

    public function setOwnerName(string $ownerName): void
    {
        $this->ownerName = $ownerName;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getProduct(): string
    {
        return $this->product;
    }

    public function setProduct(string $product): void
    {
        $this->product = $product;
    }

    public function getAccountType(): string
    {
        return $this->accountType;
    }

    public function setAccountType(string $accountType): void
    {
        $this->accountType = $accountType;
    }

    public function getBalances(): array
    {
        return $this->balances;
    }

    public function setBalances(array $balances): void
    {
        $this->balances = $balances;
    }

    public function getFullName(): string
    {
        if ('' !== $this->displayName) {
            return $this->displayName;
        }
        if ('' !== $this->ownerName) {
            return $this->ownerName;
        }
        if ('' !== $this->iban) {
            return $this->iban;
        }
        Log::warning('Account::getFullName(): no field with name, return "(no name)"');

        return '(no name)';
    }

    public function getIdentifier(): string
    {
        return $this->uid;
    }

    public function toLocalArray(): array
    {
        return [
            'uid' => $this->uid,
            'iban' => $this->iban,
            'bban' => $this->bban,
            'currency' => $this->currency,
            'owner_name' => $this->ownerName,
            'display_name' => $this->displayName,
            'product' => $this->product,
            'account_type' => $this->accountType,
            'balances' => $this->balances,
        ];
    }
}
