<?php


/*
 * GetTransactionsRequest.php
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

declare(strict_types=1);

namespace App\Services\Akahu\Request;

use App\Services\Akahu\Response\GetTransactions\GetTransactionsResponse;
use App\Services\Akahu\Response\GetTransactions\GetTransactionsResponseBuilder;

final class GetTransactionsRequest extends Request
{
    private string $akahuId;
    //    private ?Carbon $startDate;
    //    private ?Carbon $endDate;
    private AkahuDateRange $dateRange;

    public function __construct(string $akahuId, AkahuDateRange $dateRange)
    {
        parent::__construct();

        $this->akahuId   = $akahuId;
        $this->dateRange = $dateRange;
    }

    public function get(): GetTransactionsResponse
    {
        $responseBuilder = new GetTransactionsResponseBuilder();
        $cursor          = null;

        // Akahu's transaction endpoint implements pagination
        do {
            // https://developers.akahu.nz/docs/accessing-transactional-data#getting-a-date-range
            if (null !== $this->dateRange->startDate()) {
                $this->setQueryParam('start', $this->dateRange->startDate()->toISOString());
            }

            if (null !== $this->dateRange->endDate()) {
                $this->setQueryParam('end', $this->dateRange->endDate()->toISOString());
            }

            if (null !== $cursor) {
                $this->setQueryParam('cursor', $cursor);
            }

            $responseJson = $this->authenticatedGet(sprintf('accounts/%s/transactions', $this->akahuId));

            $cursor       = $responseBuilder->submitPageAndGetNextCursor($responseJson);
        } while (null !== $cursor);

        return $responseBuilder->build();
    }
}
