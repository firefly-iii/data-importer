<?php
/**
 * Transaction.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III Spectre importer
 * (https://github.com/firefly-iii/spectre-importer).
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

namespace App\Services\Spectre\Model;


use Carbon\Carbon;

/**
 * Class Transaction
 */
class Transaction
{
    public string                 $accountId;
    public string                 $amount;
    public string                 $category;
    public Carbon                 $createdAt;
    public string                 $currencyCode;
    public string                 $description;
    public bool                   $duplicated;
    public TransactionExtra       $extra;
    public string                 $id;
    public Carbon                 $madeOn;
    public string                 $mode;
    public string                 $status;
    public Carbon                 $updatedAt;

    /**
     * Transaction constructor.
     */
    private function __construct()
    {
    }

    /**
     * Transaction constructor.
     *
     * @param array $data
     */
    public static function fromArray(array $data): self
    {
        $model               = new self;
        $model->id           = (string)$data['id'];
        $model->mode         = $data['mode'];
        $model->status       = $data['status'];
        $model->madeOn       = new Carbon($data['made_on']);
        $model->amount       = (string)$data['amount'];
        $model->currencyCode = $data['currency_code'];
        $model->description  = $data['description'];
        $model->category     = $data['category'];
        $model->duplicated   = $data['duplicated'];
        $model->extra        = TransactionExtra::fromArray($data['extra'] ?? []);
        $model->accountId    = $data['account_id'];
        $model->createdAt    = new Carbon($data['created_at']);
        $model->updatedAt    = new Carbon($data['updated_at']);

        return $model;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'            => (string)$this->id,
            'account_id'    => $this->accountId,
            'made_on'       => $this->madeOn ? $this->madeOn->toW3cString() : '',
            'created_at'    => $this->createdAt ? $this->createdAt->toW3cString() : '',
            'updated_at'    => $this->updatedAt ? $this->updatedAt->toW3cString() : '',
            'mode'          => $this->mode,
            'status'        => $this->status,
            'amount'        => $this->amount,
            'currency_code' => $this->currencyCode,
            'description'   => (string)$this->description,
            'category'      => $this->category,
            'duplicated'    => $this->duplicated,
            'extra'         => $this->extra->toArray(),
        ];
    }
}
