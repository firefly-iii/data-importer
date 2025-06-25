<?php

/*
 * TransactionExtra.php
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

namespace App\Services\Spectre\Model;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
    private ?string $payeeInformation;
    private ?string $payer;
    private ?string $payerInformation;
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
     * @throws \Exception
     */
    private function __construct() {}

    /**
     * TransactionExtra constructor.
     */
    public static function fromArray(array $data): self
    {
        $model                           = new self();
        $model->id                       = $data['id'] ?? null;
        $model->recordNumber             = $data['record_number'] ?? null;
        $model->information              = $data['information'] ?? null;
        // "Time when the transaction was made."
        $model->time                     = array_key_exists('time', $data) ? new Carbon($data['time']) : null;
        // "Date when the transaction appears in statement."
        $model->postingDate              = array_key_exists('posting_date', $data) ? new Carbon($data['posting_date']) : null;
        // "Time in HH:MM:SS format, representing time when the transaction appears in statement."
        $model->postingTime              = array_key_exists('posting_time', $data) ? $data['posting_time'] : null;
        $model->accountNumber            = $data['account_number'] ?? null;
        $model->originalAmount           = isset($data['original_amount']) ? (string) $data['original_amount'] : null;
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
        $model->payeeInformation         = $data['payee_information'] ?? null;
        $model->payer                    = $data['payer'] ?? null;
        $model->payerInformation         = $data['payer_information'] ?? null;
        $model->type                     = $data['type'] ?? null;
        $model->checkNumber              = $data['check_number'] ?? null;
        $model->units                    = array_key_exists('units', $data) ? (string) $data['units'] : null;
        $model->additional               = $data['additional'] ?? null;
        $model->unitPrice                = $data['unit_price'] ?? null;
        $model->accountBalanceSnapshot   = array_key_exists('account_balance_snapshot', $data) ? (string) $data['account_balance_snapshot'] : null;
        $model->categorizationConfidence = array_key_exists('categorization_confidence', $data) ? (string) $data['categorization_confidence'] : null;

        // if has posting time, then set this time in the posting date?
        Log::debug(sprintf('Time is         "%s"', $data['time'] ?? ''));
        Log::debug(sprintf('Posting date is "%s"', $data['posting_date'] ?? ''));
        Log::debug(sprintf('Posting time is "%s"', $data['posting_time'] ?? ''));

        return $model;
    }

    public function getAdditional(): ?string
    {
        return $this->additional;
    }

    public function getInformation(): ?string
    {
        return $this->information;
    }

    public function getPayee(): ?string
    {
        return $this->payee;
    }

    public function getPayeeInformation(): ?string
    {
        return $this->payeeInformation;
    }

    public function getPayer(): ?string
    {
        return $this->payer;
    }

    public function getPayerInformation(): ?string
    {
        return $this->payerInformation;
    }

    public function getPostingDate(): ?Carbon
    {
        return $this->postingDate;
    }

    public function getPostingTime(): ?Carbon
    {
        return $this->postingTime;
    }

    public function getTime(): ?Carbon
    {
        return $this->time;
    }

    public function toArray(): array
    {
        return [
            'id'                        => $this->id,
            'time'                      => $this->time ? $this->time->toW3cString() : '',
            'posting_date'              => $this->postingDate ? $this->postingDate->toW3cString() : '',
            'posting_time'              => $this->postingTime,
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
            'payee_information'         => $this->payeeInformation,
            'payer'                     => $this->payer,
            'payer_information'         => $this->payerInformation,
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
