<?php

/*
 * Transaction.php
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

namespace App\Services\LunchFlow\Model;

use Carbon\Carbon;

/**
 * Class Transaction
 */
class Transaction
{
    public string $id;
    public int    $account;
    public string $amount;
    public string $currency;
    public Carbon $date;
    public string $description;
    public string $merchant;

    /**
     * Creates a transaction from a downloaded array.
     *
     * @param mixed $array
     */
    public static function fromArray($array): self
    {
        $object              = new self();
        // mandatory fields:
        $object->id          = $array['id'];
        $object->account     = $array['accountId'];
        $object->amount      = (string)$array['amount'];
        $object->currency    = $array['currency'];
        $object->date        = Carbon::parse($array['date'], config('app.timezone'));
        $object->description = trim($array['description'] ?? '');
        $object->merchant    = trim($array['merchant'] ?? '');

        return $object;
    }

    /**
     * @return static
     */
    public static function fromLocalArray(array $array): self
    {
        $object              = new self();

        // mandatory fields:
        $object->id          = $array['id'];
        $object->account     = $array['account'];
        $object->amount      = $array['amount'];
        $object->currency    = $array['currency'];
        $object->date        = $array['date'];
        $object->description = $array['description'];
        $object->merchant    = $array['merchant'];

        return $object;
    }

    public function getDate(): Carbon
    {
        return $this->date;
    }

    /**
     * Return transaction description, which depends on the values in the object:
     */
    public function getDescription(): string
    {
        if ('' === $this->description) {
            return '(empty description)';
        }

        return $this->description;
    }

    public function getTransactionId(): string
    {
        return $this->id;
    }

    /**
     * Return name of the destination account
     */
    public function getDestinationName(): ?string
    {
        if ('' === $this->merchant) {
            return '(empty destination)';
        }

        return $this->merchant;
    }

    /**
     * Call this "toLocalArray" because we want to confusion with "fromArray", which is really based
     * on Lunch Flow information. Likewise, there is also "fromLocalArray".
     */
    public function toLocalArray(): array
    {
        return [
            'id'          => $this->id,
            'account'     => $this->account,
            'amount'      => $this->amount,
            'currency'    => $this->currency,
            'date'        => $this->date,
            'description' => $this->description,
            'merchant'    => $this->merchant,
        ];
    }
}
