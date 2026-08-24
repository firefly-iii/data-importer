<?php

/*
 * Meta.php
 * Copyright (c) 2026 james@firefly-iii.org
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

namespace App\Services\Akahu\Model\Transaction;

// https://developers.akahu.nz/docs/the-transaction-model#meta
// Additional metadata we manage to retrieve for the transaction. Only present
// when you have permission to view enriched transactions. All fields are optional
// and provided on a "best-effort" basis.
final class Meta
{
    // The particulars field set on this transaction.
    private ?string $particulars    = null;

    // The code field set on this transaction.
    private ?string $code           = null;

    // The reference field set on this transaction.
    private ?string $reference      = null;

    // The formatted NZ bank account number of the other party to this transaction
    private ?string $otherAccount   = null;

    // If this transaction was made in another currency, details about the currency conversion.
    private ?Conversion $conversion = null;

    // If this transaction was made with a credit or debit card, the last four digits of the
    // card number.
    private ?string $cardSuffix     = null;

    // URL of a .png image for this transaction. This is typically the logo of the transaction
    // merchant. If no logo is available, a placeholder image is provided.
    private ?string $logo           = null;

    /**
     * Parse a meta structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $meta               = new self();

        $meta->particulars  = $json['particulars'] ?? null;
        $meta->code         = $json['code'] ?? null;
        $meta->reference    = $json['reference'] ?? null;

        $meta->otherAccount = $json['other_account'] ?? null;

        $meta->conversion   = array_key_exists('conversion', $json) ? Conversion::fromJson($json['conversion']) : null;

        $meta->cardSuffix   = $json['card_suffix'] ?? null;

        $meta->logo         = $json['logo'] ?? null;

        return $meta;
    }

    public function getParticulars(): ?string
    {
        return $this->particulars;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getOtherAccount(): ?string
    {
        return $this->otherAccount;
    }

    public function getConversion(): ?Conversion
    {
        return $this->conversion;
    }

    public function getCardSuffix(): ?string
    {
        return $this->cardSuffix;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }
}
