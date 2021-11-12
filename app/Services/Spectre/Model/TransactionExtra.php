<?php
/**
 * TransactionExtra.php
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
use Exception;

/**
 * Class TransactionExtra
 */
class TransactionExtra
{
    private ?string $accountBalanceSnapshot;
    private ?string $accountNumber;
    private ?string $additional;
    private ?string $assetAmount;
    private ?string $assetCode;
    private ?string $categorizationConfidence;
    private ?string $checkNumber;
    private ?string $customerCategoryCode;
    private ?string $customerCategoryName;
    private ?string $id;
    private ?string $information;
    private ?string $mcc;
    private ?string $originalAmount;
    private ?string $originalCategory;
    private ?string $originalCurrencyCode;
    private ?string $originalSubCategory;
    private ?string $payee;
    private ?bool   $possibleDuplicate;
    private ?Carbon $postingDate;
    private ?Carbon $postingTime;
    private ?string $recordNumber;
    private ?array  $tags;
    private ?Carbon $time;
    private ?string $type;
    private ?string $unitPrice;
    private ?string $units;

    /**
     * TransactionExtra constructor.
     *
     * @throws Exception
     */
    private function __construct()
    {
    }

    /**
     * TransactionExtra constructor.
     *
     * @param array $data
     */
    public static function fromArray(array $data): self
    {
        $model                           = new self;
        $model->id                       = $data['id'] ?? null;
        $model->recordNumber             = $data['record_number'] ?? null;
        $model->information              = $data['information'] ?? null;
        $model->time                     = isset($data['time']) ? new Carbon($data['time']) : null;
        $model->postingDate              = isset($data['posting_date']) ? new Carbon($data['posting_date']) : null;
        $model->postingTime              = isset($data['posting_time']) ? new Carbon($data['posting_time']) : null;
        $model->accountNumber            = $data['account_number'] ?? null;
        $model->originalAmount           = isset($data['original_amount']) ? (string)$data['original_amount'] : null;
        $model->originalCurrencyCode     = $data['original_currency_code'] ?? null;
        $model->assetCode                = $data['asset_code'] ?? null;
        $model->assetAmount              = $data['asset_amount'] ?? null;
        $model->originalCategory         = $data['original_category'] ?? null;
        $model->originalSubCategory      = $data['original_subcategory'] ?? null;
        $model->customerCategoryCode     = $data['customer_category_code'] ?? null;
        $model->customerCategoryName     = $data['customer_category_name'] ?? null;
        $model->possibleDuplicate        = $data['possible_duplicate'] ?? null;
        $model->tags                     = $data['tags'] ?? null;
        $model->mcc                      = $data['mcc'] ?? null;
        $model->payee                    = $data['payee'] ?? null;
        $model->type                     = $data['type'] ?? null;
        $model->checkNumber              = $data['check_number'] ?? null;
        $model->units                    = $data['units'] ?? null;
        $model->additional               = $data['additional'] ?? null;
        $model->unitPrice                = $data['unit_price'] ?? null;
        $model->accountBalanceSnapshot   = isset($data['account_balance_snapshot']) ? (string)$data['account_balance_snapshot'] : null;
        $model->categorizationConfidence = isset($data['categorization_confidence']) ? (string)$data['categorization_confidence'] : null;

        return $model;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'                        => $this->id,
            'time'                      => $this->time ? $this->time->toW3cString() : '',
            'posting_date'              => $this->postingDate ? $this->postingDate->toW3cString() : '',
            'posting_time'              => $this->postingTime ? $this->postingTime->toW3cString() : '',
            'record_number'             => $this->recordNumber,
            'information'               => $this->information,
            'account_number'            => $this->accountNumber,
            'original_amount'           => $this->originalAmount,
            'original_currency_code'    => $this->originalCurrencyCode,
            'asset_code'                => $this->assetCode,
            'asset_amount'              => $this->assetAmount,
            'original_category'         => $this->originalCategory,
            'original_subcategory'      => $this->originalSubCategory,
            'customer_category_code'    => $this->customerCategoryCode,
            'customer_category_name'    => $this->customerCategoryName,
            'possible_duplicate'        => $this->possibleDuplicate,
            'tags'                      => $this->tags,
            'mcc'                       => $this->mcc,
            'payee'                     => $this->payee,
            'type'                      => $this->type,
            'check_number'              => $this->checkNumber,
            'units'                     => $this->units,
            'additional'                => $this->additional,
            'unit_price'                => $this->unitPrice,
            'account_balance_snapshot'  => $this->accountBalanceSnapshot,
            'categorization_confidence' => $this->categorizationConfidence,
        ];
    }
}
