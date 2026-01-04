<?php

declare(strict_types=1);
/*
 * UserInstitution.php
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

class UserInstitution
{
    public string $userInstitutionId = '';
    public string $userId            = '';
    public string $institutionId     = '';
    public string $userName          = '';
    public string $companyName       = '';
    public string $ownerName         = '';
    public string $address           = '';
    public string $phone             = '';
    public string $email             = '';
    public array  $accounts          = [];
    public Carbon $lastModified;

    public static function fromArray(array $data): self
    {
        $object                    = new self();
        $object->userInstitutionId = $data['UserInstitutionID'];
        $object->userId            = $data['UserID'];
        $object->institutionId     = $data['InstitutionID'];
        $object->userName          = $data['UserName'] ?? '';
        $object->companyName       = $data['CompanyName'] ?? '';
        $object->ownerName         = $data['OwnerName'] ?? '';
        $object->address           = $data['Address'] ?? '';
        $object->phone             = $data['Phone'] ?? '';
        $object->email             = $data['Email'] ?? '';
        $object->accounts          = [];
        if (array_key_exists('Accounts', $data)) {
            foreach ($data['Accounts'] as $item) {
                $object->accounts[] = UserInstitutionAccount::fromArray($item);
            }
        }

        $object->lastModified      = Carbon::parse($data['LastModified']);

        return $object;
    }

    public function toArray(): array
    {
        $accounts = [];

        /** @var UserInstitutionAccount $account */
        foreach ($this->accounts as $account) {
            $accounts[] = $account->toArray();
        }

        return [
            'UserInstitutionID' => $this->userInstitutionId,
            'UserID'            => $this->userId,
            'InstitutionID'     => $this->institutionId,
            'UserName'          => $this->userName,
            'CompanyName'       => $this->companyName,
            'OwnerName'         => $this->ownerName,
            'Address'           => $this->address,
            'Phone'             => $this->phone,
            'Email'             => $this->email,
            'Accounts'          => $accounts,
            'LastModified'      => $this->lastModified->toW3cString(),
        ];
    }

    public function toArrayWithoutAccounts(): array
    {
        return [
            'UserInstitutionID' => $this->userInstitutionId,
            'UserID'            => $this->userId,
            'InstitutionID'     => $this->institutionId,
            'UserName'          => $this->userName,
            'CompanyName'       => $this->companyName,
            'OwnerName'         => $this->ownerName,
            'Address'           => $this->address,
            'Phone'             => $this->phone,
            'Email'             => $this->email,
            'Accounts'          => [],
            'LastModified'      => $this->lastModified->toW3cString(),
        ];
    }
}
