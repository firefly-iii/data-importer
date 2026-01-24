<?php

/*
 * ASPSPsResponse.php
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

use App\Services\EnableBanking\Model\Bank;
use App\Services\Shared\Response\Response;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Class ASPSPsResponse
 */
class ASPSPsResponse extends Response implements Countable, IteratorAggregate
{
    /** @var Bank[] */
    private array $banks = [];

    public function __construct(array $data = [])
    {
        $aspsps = $data['aspsps'] ?? $data;
        foreach ($aspsps as $aspsp) {
            $this->banks[] = Bank::fromArray($aspsp);
        }
    }

    public static function fromArray(array $array): self
    {
        return new self($array);
    }

    public function getBanks(): array
    {
        $banks = $this->banks;
        usort($banks, fn($a, $b) => strcasecmp($a->name, $b->name));
        return $banks;
    }

    public function count(): int
    {
        return count($this->banks);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->banks);
    }
}
