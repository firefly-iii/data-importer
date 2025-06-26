<?php

/*
 * Transaction.php
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
 * Class Transaction
 */
class Transaction
{
    private readonly string $id;
    private readonly int $posted;
    private readonly string $amount;
    private readonly string $description;
    private readonly ?int $transactedAt;
    private readonly bool $pending;
    private array $extra;

    public function __construct(array $data)
    {
        $this->validateRequiredFields($data);

        $this->id           = $data['id'];
        $this->posted       = $data['posted'];
        $this->amount       = $data['amount'];
        $this->description  = $data['description'];
        $this->transactedAt = $data['transacted_at'] ?? null;
        $this->pending      = $data['pending'] ?? false;
        $this->extra        = $data['extra'] ?? [];
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPosted(): int
    {
        return $this->posted;
    }

    public function getPostedAsCarbon(): ?Carbon
    {
        return 0 === $this->posted ? null : Carbon::createFromTimestamp($this->posted);
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getAmountAsFloat(): float
    {
        return (float) $this->amount;
    }

    public function isDeposit(): bool
    {
        return $this->getAmountAsFloat() >= 0;
    }

    public function isWithdrawal(): bool
    {
        return $this->getAmountAsFloat() < 0;
    }

    public function getAbsoluteAmount(): float
    {
        return abs($this->getAmountAsFloat());
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getTransactedAt(): ?int
    {
        return $this->transactedAt;
    }

    public function getTransactedAtAsCarbon(): ?Carbon
    {
        return null !== $this->transactedAt && 0 !== $this->transactedAt ? Carbon::createFromTimestamp($this->transactedAt) : null;
    }

    public function isPending(): bool
    {
        return $this->pending;
    }

    public function isPosted(): bool
    {
        return !$this->pending && $this->posted > 0;
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

    public function getEffectiveDate(): Carbon
    {
        // Use transacted_at if available, otherwise fall back to posted date
        if ($this->transactedAt && $this->transactedAt > 0) {
            return Carbon::createFromTimestamp($this->transactedAt);
        }

        if ($this->posted > 0) {
            return Carbon::createFromTimestamp($this->posted);
        }

        // If both are 0 or invalid, return current time
        return Carbon::now();
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'posted'        => $this->posted,
            'amount'        => $this->amount,
            'description'   => $this->description,
            'transacted_at' => $this->transactedAt,
            'pending'       => $this->pending,
            'extra'         => $this->extra,
        ];
    }

    private function validateRequiredFields(array $data): void
    {
        $requiredFields = ['id', 'posted', 'amount', 'description'];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new \InvalidArgumentException(sprintf('Missing required field: %s', $field));
            }
        }

        // Validate posted is numeric
        if (!is_numeric($data['posted'])) {
            throw new \InvalidArgumentException('Posted date must be a numeric timestamp');
        }

        // Validate amount is numeric string
        if (!is_numeric($data['amount'])) {
            throw new \InvalidArgumentException('Amount must be a numeric string');
        }

        // Validate transacted_at if present
        if (isset($data['transacted_at']) && !is_numeric($data['transacted_at'])) {
            throw new \InvalidArgumentException('Transacted at must be a numeric timestamp');
        }

        // Validate pending if present
        if (isset($data['pending']) && !is_bool($data['pending'])) {
            throw new \InvalidArgumentException('Pending must be a boolean');
        }
    }
}
