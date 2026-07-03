<?php

/*
 * GetAccountsResponse.php
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

namespace App\Services\OpenBankingIo\Response;

use App\Services\OpenBankingIo\Model\Account;
use App\Services\Shared\Response\Response;
use Countable;
use Illuminate\Support\Collection;
use Iterator;

/**
 * Class GetAccountsResponse
 *
 * @implements Iterator<int, Account>
 */
final class GetAccountsResponse extends Response implements Iterator, Countable
{
    private readonly Collection $collection;
    private int $position = 0;

    public function __construct(array $data)
    {
        $this->collection = new Collection();
        foreach ($data as $entry) {
            $this->collection->push(Account::fromArray($entry));
        }
    }

    public function count(): int
    {
        return $this->collection->count();
    }

    public function current(): Account
    {
        return $this->collection->get($this->position);
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return $this->collection->has($this->position);
    }
}
