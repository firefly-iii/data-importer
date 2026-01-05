<?php

declare(strict_types=1);
/*
 * PostGetTransactionsByTransactionDate.php
 * Copyright (c) 2026 james@firefly-iii.org
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

namespace App\Services\Sophtron\Request;

use App\Services\Shared\Response\Response;
use App\Services\Sophtron\Response\PostGetTransactionsByTransactionDateResponse;

class PostGetTransactionsByTransactionDateRequest extends Request
{
    private string $start     = '';
    private string $end       = '';
    private string $accountId = '';

    public function __construct(string $userId, string $accessKey, string $accountId, string $start, string $end)
    {
        $this->userId    = $userId;
        $this->accessKey = $accessKey;
        $this->accountId = $accountId;
        $this->start     = $start;
        $this->end       = $end;
        $this->url       = 'api/transaction/getTransactionsByTransactionDate';
        $this->method    = 'POST';
        $this->calculateAuthString();

    }

    public function get(): Response
    {
        //  Implement get() method.
    }

    public function post(): Response
    {
        $body   = [
            'accountID' => $this->accountId,
            'startDate' => '1970-01-01',
            'endDate'   => date('Y-m-d'),
        ];
        if ('' !== $this->start) {
            $body['startDate'] = $this->start;
        }
        if ('' !== $this->end) {
            $body['endDate'] = $this->end;
        }
        $result = $this->authenticatedPost($body);

        return new PostGetTransactionsByTransactionDateResponse($result);
        // Implement post() method.
    }

    public function put(): Response
    {
        // Implement put() method.
    }
}
