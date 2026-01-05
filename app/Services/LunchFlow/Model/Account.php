<?php

/*
 * Account.php
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

/**
 * Class Account
 */
class Account
{
    public int     $id;
    public string  $institutionLogo;
    public string  $institutionName;
    public string  $name;
    public string  $provider;
    public ?string $currency = null;
    public ?string $status   = null;

    /**
     * Account constructor.
     */
    public function __construct() {}

    /**
     * @return static
     */
    public static function fromArray(array $data): self
    {
        $model                  = new self();
        $model->id              = $data['id'];
        $model->institutionLogo = $data['institution_logo'];
        $model->institutionName = $data['institution_name'];
        $model->name            = $data['name'];
        $model->provider        = $data['provider'];
        $model->currency        = $data['currency'] ?? null;
        $model->status          = $data['status'] ?? null;

        return $model;
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'institution_logo' => $this->institutionLogo,
            'institution_name' => $this->institutionName,
            'name'             => $this->name,
            'provider'         => $this->provider,
            'currency'         => $this->currency,
            'status'           => $this->status,
            'class'            => self::class,
        ];
    }
}
