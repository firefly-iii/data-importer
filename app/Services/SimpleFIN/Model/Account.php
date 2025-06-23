<?php

/*
 * Account.php
 * Copyright (c) 2021 james@firefly-iii.org
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

namespace App\Services\SimpleFIN\Model;

use Carbon\Carbon;

/**
 * Class Account
 */
class Account
{
    private array $org;
    private string $id;
    private string $name;
    private string $currency;
    private string $balance;
    private ?string $availableBalance;
    private int $balanceDate;
    private array $transactions;
    private array $extra;

    public function __construct(array $data)
    {
        $this->validateRequiredFields($data);

        $this->org = $data['org'];
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->currency = $data['currency'];
        $this->balance = $data['balance'];
        $this->availableBalance = $data['available-balance'] ?? null;
        $this->balanceDate = $data['balance-date'];
        $this->transactions = $data['transactions'] ?? [];
        $this->extra = $data['extra'] ?? [];
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getOrganization(): array
    {
        return $this->org;
    }

    public function getOrganizationDomain(): ?string
    {
        return $this->org['domain'] ?? null;
    }

    public function getOrganizationName(): ?string
    {
        return $this->org['name'] ?? null;
    }

    public function getOrganizationSfinUrl(): string
    {
        return $this->org['sfin-url'];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function isCustomCurrency(): bool
    {
        return str_starts_with($this->currency, 'http://') || str_starts_with($this->currency, 'https://');
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function getBalanceAsFloat(): float
    {
        return (float) $this->balance;
    }

    public function getAvailableBalance(): ?string
    {
        return $this->availableBalance;
    }

    public function getAvailableBalanceAsFloat(): ?float
    {
        return $this->availableBalance ? (float) $this->availableBalance : null;
    }

    public function getBalanceDate(): int
    {
        return $this->balanceDate;
    }

    public function getBalanceDateAsCarbon(): Carbon
    {
        return Carbon::createFromTimestamp($this->balanceDate);
    }

    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function getTransactionCount(): int
    {
        return count($this->transactions);
    }

    public function hasTransactions(): bool
    {
        return !empty($this->transactions);
    }

    public function getExtra(): array
    {
        return $this->extra;
    }

    public function getExtraValue(string $key): mixed
    {
        return $this->extra[$key] ?? null;
    }

    public function hasExtra(string $key): bool
    {
        return array_key_exists($key, $this->extra);
    }

    public function toArray(): array
    {
        return [
            'org' => $this->org,
            'id' => $this->id,
            'name' => $this->name,
            'currency' => $this->currency,
            'balance' => $this->balance,
            'available-balance' => $this->availableBalance,
            'balance-date' => $this->balanceDate,
            'transactions' => $this->transactions,
            'extra' => $this->extra,
        ];
    }

    private function validateRequiredFields(array $data): void
    {
        $requiredFields = ['org', 'id', 'name', 'currency', 'balance', 'balance-date'];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new \InvalidArgumentException(sprintf('Missing required field: %s', $field));
            }
        }

        // Validate organization structure
        if (!is_array($data['org'])) {
            throw new \InvalidArgumentException('Organization must be an array');
        }

        if (!isset($data['org']['sfin-url'])) {
            throw new \InvalidArgumentException('Organization must have sfin-url');
        }

        if (!isset($data['org']['domain']) && !isset($data['org']['name'])) {
            throw new \InvalidArgumentException('Organization must have either domain or name');
        }

        // Validate balance-date is numeric
        if (!is_numeric($data['balance-date'])) {
            throw new \InvalidArgumentException('Balance date must be a numeric timestamp');
        }
    }
}