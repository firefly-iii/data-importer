<?php


/*
 * Refreshed.php
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

use Carbon\Carbon;

final class Refreshed
{
    // When the balance was last updated.
    private ?Carbon $balance      = null;

    // When other account metadata was last updated (any account property
    // apart from balance).
    private ?Carbon $meta         = null;

    // When we last checked for and processed any new transactions. This
    // flag may be missing when an account has first connected, as it takes
    // a few seconds for new transactions to be processed.
    private ?Carbon $transactions = null;

    // When we last fetched identity data about the party who has
    // authenticated with the financial institution when connecting this
    // account. This data is updated by Akahu on a fixed 30 day interval,
    // regardless of your app's data refresh configuration.
    private ?Carbon $party        = null;

    /**
     * Parse a refreshed structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $refreshed               = new self();

        $refreshed->balance      = array_key_exists('balance', $json) ? Carbon::parse($json['balance']) : null;
        $refreshed->meta         = array_key_exists('meta', $json) ? Carbon::parse($json['meta']) : null;
        $refreshed->transactions = array_key_exists('transactions', $json) ? Carbon::parse($json['transactions']) : null;
        $refreshed->party        = array_key_exists('party', $json) ? Carbon::parse($json['party']) : null;

        return $refreshed;
    }

    /**
     * Serialize a refreshed structure to store on disk
     */
    public function toArray(): array
    {
        return [
            'balance'      => $this->balance?->toISOString(),
            'meta'         => $this->meta?->toISOString(),
            'transactions' => $this->transactions?->toISOString(),
            'party'        => $this->party?->toISOString(),
        ];
    }

    /**
     * Deserialize a refreshed structure from disk
     */
    public static function fromArray(array $data): self
    {
        $refreshed               = new self();

        $refreshed->balance      = array_key_exists('balance', $data) ? Carbon::parse($data['balance']) : null;
        $refreshed->meta         = array_key_exists('meta', $data) ? Carbon::parse($data['meta']) : null;
        $refreshed->transactions = array_key_exists('transactions', $data) ? Carbon::parse($data['transactions']) : null;
        $refreshed->party        = array_key_exists('party', $data) ? Carbon::parse($data['party']) : null;

        return $refreshed;
    }

    public function getBalance(): ?Carbon
    {
        return $this->balance;
    }

    public function getMeta(): ?Carbon
    {
        return $this->meta;
    }

    public function getTransactions(): ?Carbon
    {
        return $this->transactions;
    }

    public function getParty(): ?Carbon
    {
        return $this->party;
    }
}
