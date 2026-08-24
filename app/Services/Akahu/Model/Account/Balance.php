<?php

/*
 * Balance.php
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

namespace App\Services\Akahu\Model\Account;

use App\Support\Facades\Steam;
use BcMath\Number;

final class Balance
{
    // The current account balance. A negative balance indicates the amount owed
    // to the account issuer. For example a checking account in overdraft will have
    // a negative balance, same as the amount owed on a credit card or the
    // principal remaining on a loan.
    private ?Number $current   = null;

    // The balance that is currently available to the account holder.
    private ?Number $available = null;

    // The credit limit for this account. For example a credit card limit or an
    // overdraft limit. This value is only present when provided directly by the
    // connected financial institution.
    private ?Number $limit     = null;

    // A boolean indicating whether this account is in overdraft.
    private ?bool $overdrawn   = null;

    // The 3 letter ISO 4217 currency code that this balance is in (e.g. NZD).
    private ?string $currency  = null;

    /**
     * Parse a balance structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $balance            = new self();

        $balance->current   = array_key_exists('current', $json) ? Steam::bcnumber($json['current']) : null;
        $balance->available = array_key_exists('available', $json) ? Steam::bcnumber($json['available']) : null;
        $balance->limit     = array_key_exists('limit', $json) ? Steam::bcnumber($json['limit']) : null;

        $balance->overdrawn = $json['overdrawn'] ?? null;
        $balance->currency  = $json['currency'] ?? null;

        return $balance;
    }

    /**
     * Serialize a balance structure to store on disk
     */
    public function toArray(): array
    {
        return [
            'current'   => serialize($this->current),
            'available' => serialize($this->available),
            'limit'     => serialize($this->limit),
            'overdrawn' => $this->overdrawn,
            'currency'  => $this->currency,
        ];
    }

    /**
     * Deserialize a balance structure from disk
     */
    public static function fromArray(array $data): self
    {
        $balance            = new self();

        $balance->current   = array_key_exists('current', $data) ? unserialize($data['current']) : null;
        $balance->available = array_key_exists('available', $data) ? unserialize($data['available']) : null;
        $balance->limit     = array_key_exists('limit', $data) ? unserialize($data['limit']) : null;

        $balance->overdrawn = $data['overdrawn'] ?? null;
        $balance->currency  = $data['currency'] ?? null;

        return $balance;
    }

    public function getCurrent(): ?Number
    {
        return $this->current;
    }

    public function getAvailable(): ?Number
    {
        return $this->available;
    }

    public function getLimit(): ?Number
    {
        return $this->limit;
    }

    public function getOverdrawn(): ?bool
    {
        return $this->overdrawn;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }
}
