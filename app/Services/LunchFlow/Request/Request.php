<?php

/*
 * Request.php
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

namespace App\Services\LunchFlow\Request;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Shared\Response\Response;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Class Request
 */
abstract class Request
{
    private string $base;
    private float  $timeOut = 3.14;
    private string $url;
    private string $apiKey;

    /**
     * @throws ImporterHttpException
     */
    abstract public function get(): Response;

    /**
     * @throws ImporterHttpException
     */
    abstract public function post(): Response;

    /**
     * @throws ImporterHttpException
     */
    abstract public function put(): Response;

    public function setParameters(array $parameters): void
    {
        Log::debug('setParameters', $parameters);
        $this->parameters = $parameters;
    }

    public function setTimeOut(float $timeOut): void
    {
        $this->timeOut = $timeOut;
    }

    /**
     * @throws GuzzleException
     * @throws ImporterHttpException
     */
    protected function authenticatedGet(): array
    {
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());
        $client  = $this->getClient();
        $body    = null;

        try {
            $res = $client->request(
                'GET',
                $fullUrl,
                [
                    'headers' => [
                        'Accept'       => 'application/json',
                        'Content-Type' => 'application/json',
                        'x-api-key'    => $this->apiKey,
                        'User-Agent'   => sprintf('FF3-data-importer/%s (%s)', config('importer.version'), config('importer.line_c')),
                    ],
                ]
            );
        } catch (TransferException $e) {
            Log::error(sprintf('TransferException: %s', $e->getMessage()));
            // if response, parse as error response.

            if (method_exists($e, 'hasResponse') && !$e->hasResponse()) {
                throw new ImporterHttpException(sprintf('Exception: %s', $e->getMessage()));
            }
            $body = method_exists($e, 'getResponse') ? (string)$e->getResponse()->getBody() : '';
            $exception = new ImporterHttpException(sprintf('Transfer exception leads to error: %s', $body), 0, $e);
            $exception->statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
            throw $exception;
        }
        if (200 !== $res->getStatusCode()) {
            // return body, class must handle this
            Log::error(sprintf('[3] Status code is %d', $res->getStatusCode()));

            $body = (string)$res->getBody();
        }
        $body ??= (string)$res->getBody();

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ImporterHttpException(
                sprintf(
                    'Could not decode JSON (%s). Error[%d] is: %s. Response: %s',
                    $fullUrl,
                    $res->getStatusCode(),
                    $e->getMessage(),
                    $body
                )
            );
        }

        if (null === $json) {
            throw new ImporterHttpException(sprintf('Body is empty. [4] Status code is %d.', $res->getStatusCode()));
        }
        if (config('importer.log_return_json')) {
            Log::debug('JSON', $json);
        }

        return $json;
    }

    public function getBase(): string
    {
        return $this->base;
    }

    public function setBase(string $base): void
    {
        $this->base = $base;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    private function getClient(): Client
    {
        // config here

        return new Client(
            [
                'connect_timeout' => $this->timeOut,
            ]
        );
    }


    protected function getDefaultHeaders(): array
    {
        $this->expiresAt = Carbon::now()->getTimestamp() + 180;

        return [
            'App-id'        => $this->getAppId(),
            'Secret'        => $this->getSecret(),
            'Accept'        => 'application/json',
            'Content-type'  => 'application/json',
            'Cache-Control' => 'no-cache',
            'User-Agent'    => sprintf('FF3-data-importer/%s (%s)', config('importer.version'), config('importer.line_a')),
            'Expires-at'    => $this->expiresAt,
        ];
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }


}
