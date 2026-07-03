<?php

/*
 * Transaction.php
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

use Carbon\Carbon;

/**
 * Class Transaction
 *
 * A single open-banking.io transaction with sensitive fields already decrypted.
 * The amount is stored SIGNED (negative = money out / DBIT, positive = money in / CRDT)
 * so the standard positive/negative Firefly III mapping applies directly.
 */
final class Transaction
{
    public string $id                   = '';
    public string $account              = '';
    public string $amount               = '0';
    public string $currency             = '';
    public Carbon $date;
    public string $creditDebitIndicator = '';
    public ?string $status              = null;

    public ?string $creditorName        = null;
    public ?string $creditorIban        = null;
    public ?string $debtorName          = null;
    public ?string $debtorIban          = null;
    public string $description          = '';
    public ?string $note                = null;

    /**
     * Build from a merged array of cleartext metadata + the decrypted "enc" payload.
     * The owning account id travels inside the array as "accountId".
     */
    public static function fromArray(array $data): self
    {
        $object                       = new self();
        $object->id                   = (string) ($data['id'] ?? '');
        $object->account              = (string) ($data['accountId'] ?? '');
        $object->currency             = (string) ($data['currency'] ?? '');
        $object->creditDebitIndicator = strtoupper((string) ($data['creditDebitIndicator'] ?? ''));
        $object->status               = self::nullableString($data['status'] ?? null);

        // amount magnitude comes from the decrypted payload; direction from the indicator.
        $magnitude                    = ltrim((string) ($data['amount'] ?? '0'), '-');
        if ('' === $magnitude) {
            $magnitude = '0';
        }
        $isCredit                     = 'CRDT' === $object->creditDebitIndicator;
        $object->amount               = $isCredit ? $magnitude : bcmul($magnitude, '-1', 12);

        $dateString                   = $data['bookingDate'] ?? $data['valueDate'] ?? $data['transactionDate'] ?? null;
        $object->date                 = Carbon::parse((string) ($dateString ?? 'now'), config('app.timezone'));

        $object->creditorName         = self::nullableString($data['creditorName'] ?? null);
        $object->creditorIban         = self::nullableString($data['creditorIban'] ?? null);
        $object->debtorName           = self::nullableString($data['debtorName'] ?? null);
        $object->debtorIban           = self::nullableString($data['debtorIban'] ?? null);
        $object->note                 = self::nullableString($data['note'] ?? null);
        $object->description          = trim((string) (
            $data['remittanceInformation'] ?? $data['note'] ?? $data['referenceNumber'] ?? ''
        ));

        return $object;
    }

    public function getDate(): Carbon
    {
        return $this->date;
    }

    public function getTransactionId(): string
    {
        return $this->id;
    }

    public function getDescription(): string
    {
        if ('' === $this->description) {
            return '(empty description)';
        }

        return $this->description;
    }

    /**
     * The counterparty when this account is the source (a withdrawal): the creditor.
     */
    public function getDestinationName(): string
    {
        return $this->creditorName ?? '(unknown destination account)';
    }

    /**
     * The counterparty when this account is the destination (a deposit): the debtor.
     */
    public function getSourceName(): string
    {
        return $this->debtorName ?? '(unknown source account)';
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
