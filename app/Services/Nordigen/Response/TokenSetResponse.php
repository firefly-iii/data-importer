<?php

/*
 * TokenSetResponse.php
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

use Carbon\Carbon;
use App\Services\Shared\Response\Response;

/**
 * Class TokenSetResponse
 */
class TokenSetResponse extends Response
{
    public int    $accessExpires;
    public string $accessToken;
    public int    $refreshExpires;
    public string $refreshToken;

    public function __construct(array $data)
    {
        $this->accessToken    = $data['access'];
        $this->refreshToken   = $data['refresh'];

        $this->accessExpires  = Carbon::now()->getTimestamp() + $data['access_expires'];
        $this->refreshExpires = Carbon::now()->getTimestamp() + $data['refresh_expires'];
    }
}
