<?php
/*
 * NewRequisitionResponse.php
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


namespace App\Services\Nordigen\Response;

use App\Services\Shared\Response\Response;

/**
 * Class NewUserAgreementResponse
 */
class NewUserAgreementResponse extends Response
{

    public string $id;
    public string $created;
    public int $max_historical_days;
    #public string $accepted;
    public string $institution_id;

    /**
     * @inheritDoc
     */
    public function __construct(array $data)
    {
        $this->id        = $data['id'];
        $this->created  = $data['created'];
        $this->max_historical_days    = $data['max_historical_days'];
        #$this->accepted      = $data['accepted'];
        $this->institution_id      = $data['institution_id'];
    }
}
