<?php

declare(strict_types=1);
/*
 * GetAccountsRequest.php
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

namespace App\Services\LunchFlow\Request;

use App\Exceptions\ImporterHttpException;
use App\Services\LunchFlow\Response\ErrorResponse;
use App\Services\LunchFlow\Response\GetAccountsResponse;
use App\Services\Shared\Response\Response;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class GetAccountsRequest extends Request
{
    public string $connection;

    /**
     * ListConnectionsRequest constructor.
     */
    public function __construct(string $apiKey)
    {
        $this->setUrl('accounts');
        $this->setApiKey($apiKey);
        $this->setBase(config('lunchflow.api_url'));
    }

    /**
     * @throws GuzzleException
     */
    public function get(): Response
    {
        Log::debug('GetAccountsRequest::get()');

        try {
            $response = $this->authenticatedGet();
        } catch (ImporterHttpException $e) {
            // JSON thing.
            return new ErrorResponse($e->json ?? ['statusCode' => $e->statusCode]);
        }

        return new GetAccountsResponse($response['accounts'] ?? []);
    }

    public function post(): Response
    {
        // Implement post() method.
    }

    public function put(): Response
    {
        // Implement put() method.
    }
}
