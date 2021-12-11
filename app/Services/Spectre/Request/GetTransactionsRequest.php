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

namespace App\Services\Spectre\Request;

use App\Services\Shared\Response\Response;
use App\Services\Spectre\Response\GetTransactionsResponse;

/**
 * Class GetTransactionsRequest
 */
class GetTransactionsRequest extends Request
{
    public string $accountId;
    public string $connectionId;

    /**
     * GetTransactionsRequest constructor.
     *
     * @param string $url
     * @param string $appId
     * @param string $secret
     */
    public function __construct(string $url, string $appId, string $secret)
    {
        $this->setBase($url);
        $this->setAppId($appId);
        $this->setSecret($secret);
        $this->setUrl('transactions');
    }

    /**
     * @inheritDoc
     */
    public function get(): Response
    {
        $hasNextPage  = true;
        $nextId       = 0;
        $transactions = [];
        while ($hasNextPage) {
            app('log')->debug(sprintf('Now calling GetTransactionsRequest for next_id %d', $nextId));

            $this->setParameters(
                [
                    'connection_id' => $this->connectionId,
                    'account_id'    => $this->accountId,
                    'from_id'       => $nextId,
                ]
            );
            $response = $this->authenticatedGet();

            // count entries:
            app('log')->debug(sprintf('Found %d entries in data-array', count($response['data'])));

            // extract next ID
            $hasNextPage = false;
            if (isset($response['meta']['next_id']) && (int) $response['meta']['next_id'] > $nextId) {
                $hasNextPage = true;
                $nextId      = $response['meta']['next_id'];
                app('log')->debug(sprintf('Next ID is now %d.', $nextId));
            }

            // store customers:
            foreach ($response['data'] as $transactionArray) {
                $transactions[] = $transactionArray;
            }
        }

        return new GetTransactionsResponse($transactions);
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
