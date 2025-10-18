<?php

/*
 * AccountsRequest.php
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

namespace App\Services\SimpleFIN\Request;

use App\Exceptions\ImporterHttpException;
use App\Services\SimpleFIN\Response\AccountsResponse;
use Illuminate\Support\Facades\Log;

/**
 * Class AccountsRequest
 */
class AccountsRequest extends SimpleFINRequest
{
    /**
     * @throws ImporterHttpException
     */
    public function get(): AccountsResponse
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        $response = $this->authenticatedGet('/accounts');

        return new AccountsResponse($response);
    }
}
