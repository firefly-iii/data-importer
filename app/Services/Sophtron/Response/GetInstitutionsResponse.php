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
use Countable;
use Illuminate\Support\Facades\Log;
use Iterator;

class GetInstitutionsResponse extends Response implements Iterator, Countable
{
    private array $institutions;
    private int   $position = 0;

    public function __construct(array $data)
    {
        $countCountries     = 0;
        $countInstitutions  = 0;
        $this->institutions = [];

        /** @var array $array */
        foreach ($data as $array) {
            $institution                                                     = Institution::fromArray($array);
            if (!array_key_exists($institution->countryCode, $this->institutions)) {
                ++$countCountries;
                $this->institutions[$institution->countryCode] = [
                    'country_code' => $institution->countryCode,
                    'institutions' => [],
                ];
            }
            ++$countInstitutions;
            $this->institutions[$institution->countryCode]['institutions'][] = $institution;
        }
        Log::debug(sprintf('Downloaded %d institution(s) from %d country(ies).', $countInstitutions, $countCountries));
        $this->institutions = array_values($this->institutions);
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
