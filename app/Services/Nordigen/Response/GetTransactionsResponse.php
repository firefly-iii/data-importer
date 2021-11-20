<?php
/*
 * GetTransactionsResponse.php
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

use App\Services\Nordigen\Model\Transaction;
use App\Services\Shared\Response\Response;
use Countable;
use Illuminate\Support\Collection;
use Iterator;

/**
 * Class GetTransactionsResponse
 */
class GetTransactionsResponse extends Response  implements Iterator, Countable
{
    private Collection $collection;
    private int        $position = 0;

    /**
     * @inheritDoc
     */
    public function __construct(array $data)
    {
        $this->collection= new Collection;
        /** @var array $array */
        foreach($data as $array) {
            $this->collection->push(Transaction::fromArray($array));
        }
    }

    /**
     * Count elements of an object.
     *
     * @link  https://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count(): int
    {
        return $this->collection->count();
    }

    /**
     * Return the current element.
     *
     * @link  https://php.net/manual/en/iterator.current.php
     * @return Transaction
     * @since 5.0.0
     */
    public function current(): Transaction
    {
        return $this->collection->get($this->position);
    }

    /**
     * Return the key of the current element.
     *
     * @link  https://php.net/manual/en/iterator.key.php
     * @return int
     * @since 5.0.0
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Move forward to next element.
     *
     * @link  https://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * Rewind the Iterator to the first element.
     *
     * @link  https://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Checks if current position is valid.
     *
     * @link  https://php.net/manual/en/iterator.valid.php
     * @return bool The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid(): bool
    {
        return $this->collection->has($this->position);
    }
}
