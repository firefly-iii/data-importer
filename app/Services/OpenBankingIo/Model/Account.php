<?php

/*
 * Account.php
 * Copyright (c) 2026 open-banking.io contribution to Firefly III (https://github.com/firefly-iii).
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

namespace App\Services\OpenBankingIo\Model;

/**
 * Class Account
 *
 * A single open-banking.io account with its sensitive fields already decrypted.
 */
final class Account
{
    public string $id           = '';
    public string $aspspName    = '';
    public string $aspspCountry = '';
    public string $currency     = '';
    public ?string $accountType = null;
    public ?string $bic         = null;
    public bool $needsReconnect = false;
    public ?string $iban        = null;
    public ?string $bban        = null;
    public ?string $ownerName   = null;
    public ?string $accountName = null;
    public ?string $product     = null;
    public ?string $displayName = null;

    /** @var Balance[] */
    public array $balances      = [];

    public function __construct() {}

    /**
     * Builds the model from an already-decrypted associative array (see GetAccountsResponse).
     */
    public static function fromArray(array $data): self
    {
        $model                 = new self();
        $model->id             = (string) ($data['id'] ?? '');
        $model->aspspName      = (string) ($data['aspspName'] ?? '');
        $model->aspspCountry   = (string) ($data['aspspCountry'] ?? '');
        $model->currency       = (string) ($data['currency'] ?? '');
        $model->accountType    = self::nullableString($data['accountType'] ?? null);
        $model->bic            = self::nullableString($data['bic'] ?? null);
        $model->needsReconnect = (bool) ($data['needsReconnect'] ?? false);
        $model->iban           = self::nullableString($data['iban'] ?? null);
        $model->bban           = self::nullableString($data['bban'] ?? null);
        $model->ownerName      = self::nullableString($data['ownerName'] ?? null);
        $model->accountName    = self::nullableString($data['accountName'] ?? null);
        $model->product        = self::nullableString($data['product'] ?? null);
        $model->displayName    = self::nullableString($data['displayName'] ?? null);

        $balances              = [];
        foreach (($data['balances'] ?? []) as $balance) {
            $balances[] = new Balance(
                (string) ($balance['type'] ?? ''),
                self::nullableString($balance['name'] ?? null),
                (string) ($balance['amount'] ?? '0'),
                (string) ($balance['currency'] ?? $model->currency),
                self::nullableString($balance['referenceDate'] ?? null),
            );
        }
        $model->balances       = $balances;

        return $model;
    }

    /**
     * A human-friendly name for the account selection screen.
     */
    public function getName(): string
    {
        $name = $this->displayName ?? $this->accountName ?? $this->ownerName ?? $this->iban ?? $this->id;

        return (string) $name;
    }

    /**
     * The booked balance ("ITBD"), if present.
     */
    public function getBookedBalance(): ?Balance
    {
        foreach ($this->balances as $balance) {
            if ('ITBD' === $balance->type) {
                return $balance;
            }
        }

        return $this->balances[0] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'aspspName'    => $this->aspspName,
            'aspspCountry' => $this->aspspCountry,
            'currency'     => $this->currency,
            'accountType'  => $this->accountType,
            'iban'         => $this->iban,
            'bban'         => $this->bban,
            'ownerName'    => $this->ownerName,
            'accountName'  => $this->accountName,
            'displayName'  => $this->displayName,
            'class'        => self::class,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $string = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        return '' === $string ? null : $string;
    }
}
