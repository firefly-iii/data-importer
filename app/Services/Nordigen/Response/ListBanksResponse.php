<?php

/*
 * ListBanksResponse.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
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

namespace App\Services\Nordigen\Response;

use App\Services\Nordigen\Model\Bank;
use App\Services\Nordigen\Model\Country;
use App\Services\Shared\Response\Response;
use Countable;
use Illuminate\Support\Collection;
use Iterator;

/**
 * Class ListBanksResponse
 */
class ListBanksResponse extends Response implements \Iterator, \Countable
{
    private readonly Collection $collection;
    private array      $countries;
    private int        $position = 0;

    public function __construct(array $data)
    {
        $this->countries  = [];

        foreach ($data as $bank) {
            // process countries:
            $this->processCountries($bank);

            // process bank
            $object = Bank::fromArray($bank);

            // add to country:
            $this->addToCountries($object, $bank['countries']);
        }
        ksort($this->countries);
        $this->collection = new Collection(array_values($this->countries));
    }

    private function processCountries(array $bank): void
    {
        foreach ($bank['countries'] as $code) {
            if (!isset($this->countries[$code])) {
                $this->countries[$code] = new Country($code, new Collection());
            }
        }
    }

    private function addToCountries(Bank $object, array $countries): void
    {
        /** @var string $code */
        foreach ($countries as $code) {
            $this->countries[$code]->addBank($object);
        }
    }

    /**
     * Count elements of an object.
     *
     * @see   https://php.net/manual/en/countable.count.php
     *
     * @return int The custom count as an integer.
     *             </p>
     *             <p>
     *             The return value is cast to an integer.
     *
     * @since 5.1.0
     */
    public function count(): int
    {
        return $this->collection->count();
    }

    /**
     * Return the current element.
     *
     * @see   https://php.net/manual/en/iterator.current.php
     * @since 5.0.0
     */
    public function current(): Country
    {
        return $this->collection->get($this->position);
    }

    /**
     * Return the key of the current element.
     *
     * @see   https://php.net/manual/en/iterator.key.php
     * @since 5.0.0
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Move forward to next element.
     *
     * @see   https://php.net/manual/en/iterator.next.php
     * @since 5.0.0
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Rewind the Iterator to the first element.
     *
     * @see   https://php.net/manual/en/iterator.rewind.php
     * @since 5.0.0
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Checks if current position is valid.
     *
     * @see   https://php.net/manual/en/iterator.valid.php
     *
     * @return bool The return value will be casted to boolean and then evaluated.
     *              Returns true on success or false on failure.
     *
     * @since 5.0.0
     */
    public function valid(): bool
    {
        return $this->collection->has($this->position);
    }
}
