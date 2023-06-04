<?php
/**
 * PostConnectSessionsRequest.php
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

namespace App\Services\Spectre\Request;

use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Response\Response;
use App\Services\Spectre\Response\PostConnectSessionResponse;

/**
 * Class PostConnectSessionsRequest
 */
class PostConnectSessionsRequest extends Request
{
    public string $customer;
    public string $url;

    /**
     * PostConnectSessionsRequest constructor.
     *
     * @param  string  $url
     * @param  string  $appId
     * @param  string  $secret
     */
    public function __construct(string $url, string $appId, string $secret)
    {
        $this->setBase($url);
        $this->setAppId($appId);
        $this->setSecret($secret);
        $this->setUrl('connect_sessions/create');
    }

    /**
     * @inheritDoc
     */
    public function get(): Response
    {
        // Implement get() method.
    }

    /**
     * @inheritDoc
     * @throws ImporterErrorException
     */
    public function post(): Response
    {
        $body = [
            'data' => [
                'customer_id' => $this->customer,
                'consent'     => [
                    'scopes'                    => ['account_details', 'transactions_details'],
                    'daily_refresh'             => true,
                    'include_fake_providers'    => true,
                    'show_consent_confirmation' => true,
                    'credentials_strategy'      => 'ask',
                ],
                'attempt'     => [
                    'return_to' => $this->url,
                ],

            ],
        ];

        $response = $this->sendUnsignedSpectrePost($body);

        return new PostConnectSessionResponse($response['data']);
    }

    /**
     * @inheritDoc
     */
    public function put(): Response
    {
        // Implement put() method.
    }
}
