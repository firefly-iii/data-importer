<?php

declare(strict_types=1);
/*
 * GetInstitutionsResponse.php
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

namespace App\Services\Sophtron\Response;

use App\Services\Shared\Response\Response;
use App\Services\Sophtron\Model\Institution;
use App\Services\Sophtron\Model\UserInstitution;
use Countable;
use Illuminate\Support\Facades\Log;
use Iterator;

class PostGetInstitutionsByUserResponse extends Response implements Iterator, Countable
{
    private array $institutions = [];
    private int   $position = 0;

    public function __construct(array $data)
    {
        foreach($data as $array) {
            $this->institutions[] = UserInstitution::fromArray($array);
        }
    }

    public function current(): mixed
    {
        return $this->institutions[$this->position];
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function valid(): bool
    {
        return array_key_exists($this->position, $this->institutions);
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function count(): int
    {
        return count($this->institutions);
    }
}
