<?php
/**
 * Connection.php
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
 * Class Connection
 */
class Connection
{
    public string       $categorization;
    public string       $countryCode;
    public string       $customerId;
    public string       $id;
    public Carbon       $lastSuccess;
    public ?string      $nextPossibleRefreshAt;
    public string       $providerCode;
    public string       $providerId;
    public string       $providerName;
    public string       $secret;
    public string       $status;
    public Carbon       $updatedAt;

    /**
     * Customer constructor.
     */
    private function __construct()
    {
    }

    /**
     * @param array $data
     *
     * @return static
     */
    public static function fromArray(array $data): self
    {
        $model                        = new self;
        $model->id                    = (string)$data['id'];
        $model->categorization        = $data['categorization'];
        $model->countryCode           = $data['country_code'];
        $model->customerId            = $data['customer_id'];
        $model->nextPossibleRefreshAt = $data['next_refresh_possible_at'];
        $model->providerCode          = $data['provider_code'];
        $model->providerId            = $data['provider_id'];
        $model->providerName          = $data['provider_name'];
        $model->status                = $data['status'];
        $model->lastSuccess           = new Carbon($data['last_success_at']);
        $model->updatedAt             = new Carbon($data['updated_at']);

        return $model;
    }

}
