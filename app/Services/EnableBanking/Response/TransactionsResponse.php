<?php

/*
 * TransactionsResponse.php
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

namespace App\Services\EnableBanking\Response;

use App\Services\EnableBanking\Model\Transaction;
use App\Services\Shared\Response\Response;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Class TransactionsResponse
 */
class TransactionsResponse extends Response implements Countable, IteratorAggregate
{
    /** @var Transaction[] */
    private array $transactions = [];
    private string $accountUid = '';

    public function __construct(array $data = [])
    {
        // Constructor is required by parent, but actual parsing is done in fromArray
        // to support the accountUid parameter
    }

    public static function fromArray(array $array, string $accountUid = ''): self
    {
        $response = new self($array);
        $response->accountUid = $accountUid;

        // Handle both booked and pending transactions
        $booked = $array['transactions']['booked'] ?? $array['booked'] ?? [];
        $pending = $array['transactions']['pending'] ?? $array['pending'] ?? [];

        foreach ($booked as $tx) {
            $tx['account_uid'] = $accountUid;
            $tx['status'] = 'booked';
            $response->transactions[] = Transaction::fromArray($tx);
        }

        foreach ($pending as $tx) {
            $tx['account_uid'] = $accountUid;
            $tx['status'] = 'pending';
            $response->transactions[] = Transaction::fromArray($tx);
        }

        return $response;
    }

    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function getAccountUid(): string
    {
        return $this->accountUid;
    }

    public function count(): int
    {
        return count($this->transactions);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->transactions);
    }
}
