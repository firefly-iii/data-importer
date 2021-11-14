<?php
/*
 * GetTransactionsRequest.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Nordigen importer
 * (https://github.com/firefly-iii/nordigen-importer).
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

namespace App\Services\Nordigen\Request;

use App\Services\Nordigen\Response\GetTransactionsResponse;
use App\Services\Shared\Response\Response;

/**
 * Class GetTransactionsRequest
 */
class GetTransactionsRequest extends Request
{
    private string $identifier;

    /**
     * @param string $url
     * @param string $token
     * @param string $identifier
     */
    public function __construct(string $url, string $token, string $identifier)
    {
        $this->setParameters([]);
        $this->setBase($url);
        $this->setToken($token);
        $this->setIdentifier($identifier);
        $this->setUrl(sprintf('api/v2/accounts/%s/transactions/', $identifier));
    }

    /**
     * @param string $identifier
     */
    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @inheritDoc
     */
    public function get(): Response
    {
        $response = $this->authenticatedGet();
        $keys     = ['booked', 'pending'];
        $return   = [];
        /** @var string $key */
        foreach ($keys as $key) {
            if (array_key_exists($key, $response['transactions'])) {
                $set    = $response['transactions'][$key];
                $set    = array_map(function (array $value) use ($key) {
                    $value['key'] = $key;
                    return $value;
                }, $set);
                $return = $return + $set;
            }
        }
        return new GetTransactionsResponse($return);
    }

    /**
     * @inheritDoc
     */
    public function post(): Response
    {
        // TODO: Implement post() method.
    }

    /**
     * @inheritDoc
     */
    public function put(): Response
    {
        // TODO: Implement put() method.
    }
}
