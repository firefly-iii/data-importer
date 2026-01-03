<?php
/*
 * Transaction.php
 * Copyright (c) 2026 james@firefly-iii.org
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

namespace App\Services\Sophtron\Model;

use Carbon\Carbon;

class Transaction
{
    public ?Carbon $createdDateUtc           = null;
    public string $id                       = '';
    public Carbon  $lastModifiedUtc;
    public string  $userId                   = '';
    public string  $transactionId            = '';
    public string  $userInstitutionAccountId = '';
    public string  $status                   = '';
    public string  $type                     = '';
    public string  $amount                   = '';
    public string  $currency                 = '';
    public Carbon  $date;
    public Carbon  $transactionDate;
    public Carbon  $postDate;
    public string  $description              = '';
    public string  $balance                  = '';
    public string  $merchant                 = '';
    public string  $category                 = '';
    public string  $checkNum                 = '';
    public string  $memo                     = '';
    public string  $checkImage               = '';
    public ?Carbon $lastModified             = null;

    public static function fromArray(array $array): self
    {
        $object                           = new self();
        $object->id                       = $array['ID'] ?? '';
        $object->createdDateUtc           = array_key_exists('CreatedDateUtc', $array) ? Carbon::parse($array['CreatedDateUtc']) : null;
        $object->lastModifiedUtc          = array_key_exists('LastModifiedUtc', $array) ? Carbon::parse($array['LastModifiedUtc']) : null;
        $object->userId                   = $array['UserID'];
        $object->transactionId            = $array['TransactionID'];
        $object->userInstitutionAccountId = $array['UserInstitutionAccountID'];
        $object->status                   = $array['Status'];
        $object->type                     = $array['Type'];
        $object->amount                   = (string)$array['Amount'];
        $object->currency                 = $array['Currency'] ?? '';
        $object->date                     = Carbon::parse($array['Date']);
        $object->transactionDate          = Carbon::parse($array['TransactionDate']);
        $object->postDate                 = Carbon::parse($array['PostDate']);
        $object->description              = $array['Description'];
        $object->balance                  = (string)($array['Balance'] ?? '');
        $object->merchant                 = $array['Merchant'] ?? '';
        $object->category                 = $array['Category'] ?? '';
        $object->checkNum                 = $array['CheckNum'] ?? '';
        $object->memo                     = $array['Memo'] ?? '';
        $object->checkImage               = $array['CheckImage'] ?? '';
        $object->lastModified             = array_key_exists('LastModified', $array) ? Carbon::parse($array['LastModified']) : null;
        return $object;
    }

    public function toArray(): array
    {
        return [
            'class'                    => self::class,
            'ID'                       => $this->id,
            'CreatedDateUtc'           => $this->createdDateUtc->toW3cString(),
            'LastModifiedUtc'          => $this->lastModifiedUtc->toW3cString(),
            'UserID'                   => $this->userId,
            'TransactionID'            => $this->transactionId,
            'UserInstitutionAccountID' => $this->userInstitutionAccountId,
            'Status'                   => $this->status,
            'Type'                     => $this->type,
            'Amount'                   => $this->amount,
            'Currency'                 => $this->currency,
            'Date'                     => $this->date->toW3cString(),
            'TransactionDate'          => $this->transactionDate->toW3cString(),
            'PostDate'                 => $this->postDate->toW3cString(),
            'Description'              => $this->description,
            'Balance'                  => $this->balance,
            'Merchant'                 => $this->merchant,
            'Category'                 => $this->category,
            'CheckNum'                 => $this->checkNum,
            'Memo'                     => $this->memo,
            'CheckImage'               => $this->checkImage,
            'LastModified'             => $this->lastModified->toW3cString(),
        ];
    }
}
