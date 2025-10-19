<?php

/*
 * GetTransactionsRequest.php
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

namespace App\Services\Nordigen\Request;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Exceptions\RateLimitException;
use App\Services\Nordigen\Response\GetTransactionsResponse;
use App\Services\Shared\Response\Response;
use Illuminate\Support\Facades\Log;

/**
 * Class GetTransactionsRequest
 */
class GetTransactionsRequest extends Request
{
    public function __construct(string $url, string $token, private readonly string $identifier, string $dateFrom, string $dateTo)
    {
        $params           = [];
        $pattern          = '/^(19|20)\d\d-(0[1-9]|1[012])-(0[1-9]|[12][\d]|3[01])$/';
        $result           = preg_match($pattern, $dateFrom);
        $this->setParameters([]);
        if ('' !== $dateFrom && 1 === $result) {
            $params['date_from'] = $dateFrom;
        }

        $result           = preg_match($pattern, $dateTo);
        if ('' !== $dateTo && 1 === $result) {
            $params['date_to'] = $dateTo;
        }
        if (count($params) > 0) {
            $this->setParameters($params);
        }
        $this->setBase($url);
        $this->setToken($token);
        $this->setUrl(sprintf('api/v2/accounts/%s/transactions/', $this->identifier));


    }

    /**
     * @throws AgreementExpiredException
     * @throws ImporterErrorException
     * @throws ImporterHttpException
     * @throws RateLimitException
     */
    public function get(): Response
    {
        $response     = $this->authenticatedGet();
        $keys         = ['booked', 'pending'];
        $return       = [];
        $count        = 0;
        $transactions = $response['transactions'] ?? [];
        if (!array_key_exists('transactions', $response)) {
            Log::error('No transactions found in response');
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
        $total        = count($return);
        Log::debug(sprintf('Downloaded [%d:%d] transactions from bank account "%s"', $count, $total, $this->identifier));
        $response     = new GetTransactionsResponse($return);
        $response->setAccountId($this->identifier);
        $response->processData();

        return $response;
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
