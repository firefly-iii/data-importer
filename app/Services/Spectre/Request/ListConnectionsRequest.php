<?php

/*
 * ListConnectionsRequest.php
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

namespace App\Services\Spectre\Request;

use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Response\Response;
use App\Services\Spectre\Response\ErrorResponse;
use App\Services\Spectre\Response\ListConnectionsResponse;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Class ListConnectionsRequest
 * TODO is not yet paginated.
 */
class ListConnectionsRequest extends Request
{
    public string $customer;

    /**
     * ListConnectionsRequest constructor.
     */
    public function __construct(string $url, string $appId, string $secret)
    {
        $this->setBase($url);
        $this->setAppId($appId);
        $this->setSecret($secret);
        $this->setUrl('connections');
    }

    /**
     * @throws GuzzleException
     */
    public function get(): Response
    {
        Log::debug('ListConnectionsRequest::get()');
        $this->setParameters(
            [
                'customer_id' => $this->customer,
            ]
        );

        try {
            $response = $this->authenticatedGet();
        } catch (ImporterErrorException $e) {
            // JSON thing.
            return new ErrorResponse($e->json ?? []);
        }

        return new ListConnectionsResponse($response['data']);
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
