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

namespace App\Services\EnableBanking\Request;

use App\Exceptions\ImporterHttpException;
use App\Services\EnableBanking\Response\TransactionsResponse;
use App\Services\Shared\Response\Response;

/**
 * Class GetTransactionsRequest
 * Gets transactions for an account
 */
class GetTransactionsRequest extends Request
{
    private string $accountUid;
    private ?string $dateFrom = null;
    private ?string $dateTo = null;

    public function __construct(string $url, string $accountUid, ?string $dateFrom = null, ?string $dateTo = null)
    {
        $this->setBase($url);
        $this->accountUid = $accountUid;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;

        $urlPath = sprintf('accounts/%s/transactions', $accountUid);
        $params = [];
        if (null !== $dateFrom) {
            $params['date_from'] = $dateFrom;
        }
        if (null !== $dateTo) {
            $params['date_to'] = $dateTo;
        }
        if (count($params) > 0) {
            $urlPath .= '?' . http_build_query($params);
        }

        $this->setUrl($urlPath);
    }

    /**
     * @throws ImporterHttpException
     */
    public function get(): Response
    {
        $json = $this->authenticatedGet();

        return TransactionsResponse::fromArray($json, $this->accountUid);
    }

    public function post(): Response
    {
        throw new ImporterHttpException('GetTransactionsRequest does not support POST');
    }
}
