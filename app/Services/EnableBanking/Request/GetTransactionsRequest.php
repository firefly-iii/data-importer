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
use Illuminate\Support\Facades\Log;

/**
 * Class GetTransactionsRequest
 * Gets transactions for an account
 */
final class GetTransactionsRequest extends Request
{
    private string $accountUid;

    public function __construct(string $url, string $accountUid, ?string $dateFrom = null, ?string $dateTo = null)
    {
        $this->setBase($url);
        $this->accountUid = $accountUid;

        $urlPath          = sprintf('accounts/%s/transactions', $accountUid);
        $params           = [];
        if (null !== $dateFrom) {
            $params['date_from'] = $dateFrom;
        }
        if (null !== $dateTo) {
            $params['date_to'] = $dateTo;
        }
        $this->setParameters($params);
        $this->setUrl($urlPath);
    }

    /**
     * @throws ImporterHttpException
     */
    public function get(): Response
    {
        Log::debug('Will now do Enable Banking GetTransactionsRequest');
        // create empty response
        $response        = TransactionsResponse::fromArray([], $this->accountUid);
        $haveMorePages   = true;
        $max             = 50;
        $count           = 0;
        $continuationKey = '';
        while ($haveMorePages && $count < $max) {
            Log::debug(sprintf('Now running attempt #%d', $count + 1));
            // add continuation_key
            if ('' !== $continuationKey) {
                $this->addParameter('continuation_key', $continuationKey);
                Log::debug(sprintf('Have continuation key, add to request: "%s"', $continuationKey));
            }
            // remove if empty:
            if ('' === $continuationKey) {
                $this->removeParameter('continuation_key');
                Log::debug('No continuation key set (yet), will not be added to request.');
            }

            // do an authenticated get.
            $json            = $this->authenticatedGet();

            // retrieve new key
            $continuationKey = (string) $json['continuation_key'];
            if ('' === $continuationKey) {
                Log::debug('Response contains no continuation key, this was the last page.');
                $haveMorePages = false;
            }
            if ('' !== $continuationKey) {
                Log::debug(sprintf('Response contains continuation key "%s", will be added to the next request.', $continuationKey));
            }
            // add found transactions.
            $response->appendResponse($json);

            ++$count;
        }
        Log::debug('Done with Enable Banking GetTransactionsRequest');

        return $response;
    }

    public function post(): Response
    {
        throw new ImporterHttpException('GetTransactionsRequest does not support POST');
    }
}
