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
use Safe\Exceptions\FilesystemException;

use function Safe\base64_decode;
use function Safe\json_decode;

/**
 * Class GetTransactionsRequest
 * Gets transactions for an account
 */
final class GetTransactionsRequest extends Request
{
    private string $accountUid;
    private string $fakeDataPath = '';

    public function __construct(string $url, string $accountUid, ?string $dateFrom = null, ?string $dateTo = null)
    {
        $this->setBase($url);
        $this->accountUid   = $accountUid;
        $this->fakeDataPath = storage_path(sprintf('fake-data/eb-transactions-%s.json', $accountUid));

        $urlPath            = sprintf('accounts/%s/transactions', $accountUid);
        $params             = [];
        if (null !== $dateFrom) {
            $params['date_from'] = $dateFrom;
            $this->fakeDataPath  = storage_path(sprintf('fake-data/eb-transactions-%s-%s.json', $accountUid, $dateFrom));
        }
        if (null !== $dateTo) {
            $params['date_to']  = $dateTo;
            $this->fakeDataPath = storage_path(sprintf('fake-data/eb-transactions-%s-%s-%s.json', $accountUid, $dateFrom, $dateTo));
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

        // fake data is appended here:
        // perhaps grab fake data instead?
        $grabFake        = (bool) config('importer.fake_data');
        $fakeExists      = file_exists($this->fakeDataPath);
        if ($grabFake && $fakeExists) {
            Log::debug('Will collect fake data instead of real data.');
            $content = null;

            try {
                $content = file_get_contents($this->fakeDataPath);
            } catch (FilesystemException $e) {
                Log::error(sprintf('Could not read fake data: %s', $e->getMessage()));
            }
            if (null !== $content) {
                $allJson = json_decode($content, true);
                foreach ($allJson as $json) {
                    $response->appendResponse($json);
                }

                return $response;
            }
        }

        $allJson         = [];
        $haveMorePages   = true;
        $max             = 50;
        $count           = 0;
        $continuationKey = '';
        while ($haveMorePages && $count < $max) {
            Log::debug(sprintf('Now running attempt #%d', $count + 1));
            // add continuation_key
            if ('' !== $continuationKey) {
                Log::debug(sprintf('Have continuation key, add to request: "%s"', $continuationKey));
                $this->addParameter('continuation_key', $continuationKey);
            }
            // remove if empty:
            if ('' === $continuationKey) {
                $this->removeParameter('continuation_key');
                Log::debug('No continuation key set (yet), will not be added to request.');
            }

            // do an authenticated get.
            $json            = $this->authenticatedGet();
            $allJson[]       = $json;

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
        $this->removeParameter('continuation_key');
        Log::debug('Done with Enable Banking GetTransactionsRequest');

        // store fake data in new thing:
        if ($grabFake && !$fakeExists && true === (bool) config('importer.store_fake_data')) {
            Log::debug('Will store this run as fake data to use the next time.');

            try {
                file_put_contents($this->fakeDataPath, json_encode($allJson));
            } catch (FilesystemException $e) {
                Log::error(sprintf('Could not store fake data: %s', $e->getMessage()));
            }
        }

        return $response;
    }

    public function post(): Response
    {
        throw new ImporterHttpException('GetTransactionsRequest does not support POST');
    }
}
