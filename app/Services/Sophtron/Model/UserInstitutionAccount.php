<?php

declare(strict_types=1);
/*
 * UserInstitutionAccount.php
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

class UserInstitutionAccount
{
    public string           $userInstitutionId = '';
    public string           $memberId          = '';
    public string           $accountId         = '';
    public string           $accountName       = '';
    public string           $accountNumber     = '';
    public string           $accountType       = '';
    public string           $balance           = '0';
    public string           $availableBalance  = '0';
    public string           $balanceCurrency   = '';
    public Carbon           $lastUpdated;
    public string           $status            = '';
    public string           $subType           = '';
    public string           $userId            = '';
    public string           $id                = '';
    public Carbon           $lastModifiedUtc;
    public ?UserInstitution $userInstitution   = null;

    public static function fromArray(array $array): self
    {
        $object                    = new self();
        $object->userInstitutionId = $array['UserInstitutionID'];
        $object->memberId          = $array['MemberID'];
        $object->accountId         = $array['AccountID'];
        $object->accountName       = $array['AccountName'];
        $object->accountNumber     = $array['AccountNumber'];
        $object->accountType       = $array['AccountType'];
        $object->balance           = (string)$array['Balance'];
        $object->availableBalance  = (string)($array['AvailableBalance'] ?? '');
        $object->balanceCurrency   = $array['BalanceCurrency'];
        $object->lastUpdated       = Carbon::parse($array['LastUpdated']);
        $object->status            = $array['Status'];
        $object->subType           = $array['SubType'] ?? '';
        $object->userId            = $array['UserID'] ?? '';
        $object->id                = $array['ID'];
        $object->lastModifiedUtc   = Carbon::parse($array['LastModifiedUtc']);
        if (array_key_exists('UserInstitution', $array) && null !== $array['UserInstitution']) {
            $object->userInstitution = UserInstitution::fromArray($array['UserInstitution']);
        }

        return $object;
    }

    public function toArray(): array
    {
        $userInstitution = null;
        if (null !== $this->userInstitution) {
            $userInstitution = $this->userInstitution->toArrayWithoutAccounts();
        }

        return [
            'class'             => self::class,
            'UserInstitutionID' => $this->userInstitutionId,
            'MemberID'          => $this->memberId,
            'AccountID'         => $this->accountId,
            'AccountName'       => $this->accountName,
            'AccountNumber'     => $this->accountNumber,
            'AccountType'       => $this->accountType,
            'Balance'           => $this->balance,
            'AvailableBalance'  => $this->availableBalance,
            'BalanceCurrency'   => $this->balanceCurrency,
            'LastUpdated'       => $this->lastUpdated->toW3cString(),
            'Status'            => $this->status,
            'SubType'           => $this->subType,
            'UserId'            => $this->userId,
            'ID'                => $this->id,
            'UserInstitution'   => $userInstitution,
            'LastModifiedUtc'   => $this->lastModifiedUtc->toW3cString(),
        ];
    }
}
