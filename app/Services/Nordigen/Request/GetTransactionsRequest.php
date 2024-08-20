<?php
/*
 * GetTransactionsRequest.php
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

namespace App\Services\Nordigen\Request;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Exceptions\RateLimitException;
use App\Services\Nordigen\Response\GetTransactionsResponse;
use App\Services\Shared\Response\Response;

/**
 * Class GetTransactionsRequest
 */
class GetTransactionsRequest extends Request
{
    private string $identifier;

    public function __construct(string $url, string $token, string $identifier)
    {
        $this->setParameters([]);
        $this->setBase($url);
        $this->setToken($token);
        $this->setIdentifier($identifier);
        $this->setUrl(sprintf('api/v2/accounts/%s/transactions/', $identifier));
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @return Response
     * @throws AgreementExpiredException
     * @throws ImporterErrorException
     * @throws ImporterHttpException
     * @throws RateLimitException
     */
    public function get(): Response
    {
        $response = $this->authenticatedGet();
        $keys     = ['booked', 'pending'];
        $return   = [];
        $count    = 0;
        $transactions = $response['transactions'] ?? [];
        if(!array_key_exists('transactions', $response)) {
            var_dump($response);exit;
        }
        foreach ($keys as $key) {
            if (array_key_exists($key, $transactions)) {
                $set    = $transactions[$key];
                $set    = array_map(function (array $value) use ($key) {
                    $value['key'] = $key;

                    return $value;
                }, $set);
                $count  += count($set);
                $return = array_merge($return, $set);
            }
        }
        $total    = count($return);
        app('log')->debug(sprintf('Downloaded [%d:%d] transactions', $count, $total));

        return new GetTransactionsResponse($return);
    }

    public function post(): Response
    {
        //  Implement post() method.
    }

    public function put(): Response
    {
        // Implement put() method.
    }
}
