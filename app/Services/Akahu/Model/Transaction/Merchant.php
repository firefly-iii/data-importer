<?php


/*
 * Merchant.php
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

// Akahu defines a merchant as the business who was party to this transaction.
// For example, "The Warehouse" is a merchant.
final class Merchant
{
    // The Akahu Merchant ID
    private ?string $akahuId = null;

    // The Akahu Merchant name
    private ?string $name    = null;

    // The Akahu Merchant website
    private ?string $website = null;

    // Undocumented
    // https://www.nzbn.govt.nz/
    private ?string $nzbn    = null;

    /**
     * Parse a merchant structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $merchant          = new self();

        $merchant->akahuId = $json['_id'] ?? null;
        $merchant->name    = $json['name'] ?? null;
        $merchant->website = $json['website'] ?? null;
        $merchant->nzbn    = $json['nzbn'] ?? null;

        return $merchant;
    }

    public function getAkahuId(): ?string
    {
        return $this->akahuId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function getNZBN(): ?string
    {
        return $this->nzbn;
    }
}
