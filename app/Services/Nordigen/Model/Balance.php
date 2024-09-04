<?php
/*
 * Balance.php
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

namespace App\Services\Nordigen\Model;

/**
 * Class Balance
 */
class Balance
{
    public string $amount;
    public string $currency;
    public string $date;
    public string $lastChangeDateTime;
    public string $type;

    /**
     * @return static
     */
    public static function createFromArray(array $data): self
    {
        if (count($data) > 0) {
            app('log')->debug('Create Balance from array', $data);
        }
        $self                     = new self();
        $self->amount             = trim($data['balanceAmount']['amount'] ?? '0');
        $self->currency           = trim($data['balanceAmount']['currency'] ?? '');
        $self->type               = trim($data['balanceType'] ?? '');
        $self->date               = trim($data['referenceDate'] ?? '');
        $self->lastChangeDateTime = trim($data['lastChangeDateTime'] ?? '');

        return $self;
    }

    /**
     * @return $this
     */
    public static function fromLocalArray(array $array): self
    {
        $object                     = new self();
        $object->amount             = $array['amount'];
        $object->currency           = $array['currency'];
        $object->type               = $array['type'];
        $object->date               = $array['date'];
        $object->lastChangeDateTime = $array['last_change_date_time'];

        return $object;
    }

    public function toLocalArray(): array
    {
        return [
            'amount'                => $this->amount,
            'currency'              => $this->currency,
            'type'                  => $this->type,
            'date'                  => $this->date,
            'last_change_date_time' => $this->lastChangeDateTime,
        ];
    }
}
