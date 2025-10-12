<?php

/*
 * Customer.php
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

/**
 * Class Customer
 */
class Customer
{
    public string $id;

    public string $identifier;

    public string $secret;

    /**
     * Customer constructor.
     */
    private function __construct() {}

    /**
     * @return static
     */
    public static function fromArray(array $data): self
    {
        $model             = new self();
        $model->id         = (string) $data['customer_id'];
        $model->identifier = $data['identifier'];
        $model->secret     = $data['secret'];

        return $model;
    }
}
