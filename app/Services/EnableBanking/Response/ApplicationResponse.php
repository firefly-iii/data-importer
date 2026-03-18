<?php

/*
 * AccountsResponse.php
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

use App\Services\EnableBanking\Model\Account;
use App\Services\Shared\Response\Response;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Class AccountsResponse
 */
final class ApplicationResponse extends Response implements Countable, IteratorAggregate
{
    /** @var Account[] */
    private array  $accounts  = [];
    private string $sessionId = '';
    private array  $data      = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public static function fromArray(array $array, string $sessionId = ''): self
    {
        $response            = new self($array);
        $response->sessionId = $sessionId;

        return $response;
    }

    public function getAccounts(): array
    {
        return $this->accounts;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function count(): int
    {
        return count($this->accounts);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->accounts);
    }
}
