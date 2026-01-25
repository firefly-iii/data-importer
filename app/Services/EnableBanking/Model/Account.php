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
    private string $otherIdentification = '';
    private string $otherScheme = '';
    private string $currency = '';
    private string $ownerName = '';
    private string $displayName = '';
    private string $product = '';
    private string $accountType = '';  // API: cash_account_type (CACC, CARD, CASH, LOAN, OTHR, SVGS)
    private string $usage = '';
    private string $details = '';
    private array $balances = [];

    public function __construct()
    {
    }

    public static function fromArray(array $array): self
    {
        $account = new self();
        $account->uid = $array['uid'] ?? $array['account_uid'] ?? '';

        // Handle account_id structure per API spec
        // account_id can have: iban, other (with identification and scheme_name)
        $accountId = $array['account_id'] ?? [];
        $account->iban = $accountId['iban'] ?? $array['iban'] ?? '';

        // Handle non-IBAN identification via "other" field
        if (isset($accountId['other'])) {
            $account->otherIdentification = $accountId['other']['identification'] ?? '';
            $account->otherScheme = $accountId['other']['scheme_name'] ?? '';
        }

        // Parse all_account_ids array for BBAN and other identifications
        $allAccountIds = $array['all_account_ids'] ?? [];
        foreach ($allAccountIds as $accountIdEntry) {
            $schemeName = $accountIdEntry['scheme_name'] ?? '';
            $identification = $accountIdEntry['identification'] ?? '';

            if ('BBAN' === $schemeName && '' === $account->bban) {
                $account->bban = $identification;
            }
            if ('IBAN' === $schemeName && '' === $account->iban) {
                $account->iban = $identification;
            }
        }

        $account->currency = $array['currency'] ?? '';
        $account->ownerName = $array['owner_name'] ?? $array['account_holder_name'] ?? '';
        $account->displayName = $array['display_name'] ?? $array['name'] ?? '';
        $account->product = $array['product'] ?? '';
        // API uses cash_account_type (CACC, CARD, CASH, LOAN, OTHR, SVGS)
        $account->accountType = $array['cash_account_type'] ?? $array['account_type'] ?? '';
        $account->usage = $array['usage'] ?? '';
        $account->details = $array['details'] ?? '';

        return $account;
    }

    public static function fromLocalArray(array $array): self
    {
        $account = new self();
        $account->uid = $array['uid'] ?? '';
        $account->iban = $array['iban'] ?? '';
        $account->bban = $array['bban'] ?? '';
        $account->otherIdentification = $array['other_identification'] ?? '';
        $account->otherScheme = $array['other_scheme'] ?? '';
        $account->currency = $array['currency'] ?? '';
        $account->ownerName = $array['owner_name'] ?? '';
        $account->displayName = $array['display_name'] ?? '';
        $account->product = $array['product'] ?? '';
        $account->accountType = $array['account_type'] ?? '';
        $account->usage = $array['usage'] ?? '';
        $account->details = $array['details'] ?? '';
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

    public function getOtherIdentification(): string
    {
        return $this->otherIdentification;
    }

    public function setOtherIdentification(string $otherIdentification): void
    {
        $this->otherIdentification = $otherIdentification;
    }

    public function getOtherScheme(): string
    {
        return $this->otherScheme;
    }

    public function setOtherScheme(string $otherScheme): void
    {
        $this->otherScheme = $otherScheme;
    }

    public function getUsage(): string
    {
        return $this->usage;
    }

    public function setUsage(string $usage): void
    {
        $this->usage = $usage;
    }

    public function getDetails(): string
    {
        return $this->details;
    }

    public function setDetails(string $details): void
    {
        $this->details = $details;
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
        if ('' !== $this->otherIdentification) {
            return $this->otherIdentification;
        }
        if ('' !== $this->bban) {
            return $this->bban;
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
            'class' => self::class,
            'uid' => $this->uid,
            'iban' => $this->iban,
            'bban' => $this->bban,
            'other_identification' => $this->otherIdentification,
            'other_scheme' => $this->otherScheme,
            'currency' => $this->currency,
            'owner_name' => $this->ownerName,
            'display_name' => $this->displayName,
            'product' => $this->product,
            'account_type' => $this->accountType,
            'usage' => $this->usage,
            'details' => $this->details,
            'balances' => $this->balances,
        ];
    }

    public function toArray(): array
    {
        return $this->toLocalArray();
    }
}
