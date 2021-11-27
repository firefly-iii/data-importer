<?php
declare(strict_types=1);

namespace App\Services\Spectre\Response;

use App\Services\Shared\Response\Response;
use App\Services\Spectre\Model\Transaction;
use Countable;
use Illuminate\Support\Collection;
use Iterator;

/**
 * Class GetTransactionsResponse
 */
class GetTransactionsResponse extends Response implements Iterator, Countable
{
    private Collection $collection;
    private int        $position = 0;

    /**
     * @inheritDoc
     */
    public function __construct(array $data)
    {
        $this->collection = new Collection;
        foreach ($data as $array) {
            $model = Transaction::fromArray($array);
            $this->collection->push($model);
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

    /**
     * @return array
     */
    public function toArray(): array
    {
        $return = [];
        /** @var Transaction $transaction */
        foreach ($this->collection as $transaction) {
            $return[] = $transaction->toArray();
        }

        return $return;
    }
}
