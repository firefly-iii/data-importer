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

namespace App\Services\Spectre\Request;

use Carbon\Carbon;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Shared\Response\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Log;

/**
 * Class Request
 */
abstract class Request
{
    protected int  $expiresAt = 0;
    private string $appId;
    private string $base;
    private array  $body;
    private array  $parameters;
    private string $secret;
    private float  $timeOut   = 3.14;
    private string $url;

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

    public function setBody(array $body): void
    {
        $this->body = $body;
    }

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
     * @throws ImporterErrorException
     * @throws ImporterHttpException
     */
    protected function authenticatedGet(): array
    {
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());

        if (0 !== count($this->parameters)) {
            $fullUrl = sprintf('%s?%s', $fullUrl, http_build_query($this->parameters));
        }
        $client  = $this->getClient();
        $res     = null;
        $body    = null;
        $json    = null;

        try {
            $res = $client->request(
                'GET',
                $fullUrl,
                [
                    'headers' => [
                        'Accept'        => 'application/json',
                        'Content-Type'  => 'application/json',
                        'App-id'        => $this->getAppId(),
                        'Secret'        => $this->getSecret(),
                        'User-Agent'    => sprintf('Firefly III Spectre importer / %s / %s', config('importer.version'), config('auth.line_c')),
                    ],
                ]
            );
        } catch (TransferException $e) {
            Log::error(sprintf('TransferException: %s', $e->getMessage()));
            // if response, parse as error response.

            if (method_exists($e, 'hasResponse') && !$e->hasResponse()) {
                throw new ImporterHttpException(sprintf('Exception: %s', $e->getMessage()));
            }
            $body = method_exists($e, 'getResponse') ? (string) $e->getResponse()->getBody() : '';

            throw new ImporterErrorException(sprintf('Transfer exception leads to error: %s', $body), 0, $e);
        }
        if (200 !== $res->getStatusCode()) {
            // return body, class must handle this
            Log::error(sprintf('[3] Status code is %d', $res->getStatusCode()));

            $body = (string) $res->getBody();
        }
        $body ??= (string) $res->getBody();

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
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

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }

    /**
     * @throws ImporterHttpException
     * @throws ImporterErrorException
     */
    protected function sendSignedSpectrePost(array $data): array
    {
        if ('' === $this->url) {
            throw new ImporterErrorException('No Spectre server defined');
        }
        $fullUrl                    = sprintf('%s/%s', $this->getBase(), $this->getUrl());
        $headers                    = $this->getDefaultHeaders();

        try {
            $body = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ImporterErrorException($e->getMessage());
        }

        Log::debug('Final headers for spectre signed POST request:', $headers);

        try {
            $client = $this->getClient();
            $res    = $client->request('POST', $fullUrl, ['headers' => $headers, 'body' => $body]);
        } catch (\Exception|GuzzleException $e) {
            throw new ImporterHttpException(sprintf('Guzzle Exception: %s', $e->getMessage()));
        }

        try {
            $body = $res->getBody()->getContents();
        } catch (\RuntimeException $e) {
            Log::error(sprintf('sendSignedSpectrePost: Could not get body from SpectreRequest::POST result: %s', $e->getMessage()));
            $body = '{}';
        }

        $statusCode                 = $res->getStatusCode();
        $responseHeaders            = $res->getHeaders();

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ImporterHttpException($e->getMessage());
        }
        $json['ResponseHeaders']    = $responseHeaders;
        $json['ResponseStatusCode'] = $statusCode;

        return $json;
    }

    protected function getDefaultHeaders(): array
    {
        $userAgent       = sprintf('FireflyIII Spectre v%s', config('spectre.version'));
        $this->expiresAt = Carbon::now()->getTimestamp() + 180;

        return [
            'App-id'        => $this->getAppId(),
            'Secret'        => $this->getSecret(),
            'Accept'        => 'application/json',
            'Content-type'  => 'application/json',
            'Cache-Control' => 'no-cache',
            'User-Agent'    => $userAgent,
            'Expires-at'    => $this->expiresAt,
        ];
    }

    /**
     * @throws ImporterHttpException
     * @throws ImporterErrorException
     */
    protected function sendUnsignedSpectrePost(array $data): array
    {
        if ('' === $this->url) {
            throw new ImporterErrorException('No Spectre server defined');
        }
        $fullUrl                    = sprintf('%s/%s', $this->getBase(), $this->getUrl());
        $headers                    = $this->getDefaultHeaders();
        $opts                       = ['headers' => $headers];
        $body                       = null;

        try {
            $body = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::error($e->getMessage());
        }
        if ('{}' !== (string) $body) {
            $opts['body'] = $body;
        }

        Log::debug('Final headers for spectre UNsigned POST request:', $headers);

        try {
            $client = $this->getClient();
            $res    = $client->request('POST', $fullUrl, $opts);
        } catch (\Exception|GuzzleException $e) {
            Log::error($e->getMessage());

            throw new ImporterHttpException(sprintf('Guzzle Exception: %s', $e->getMessage()));
        }

        try {
            $body = $res->getBody()->getContents();
        } catch (\RuntimeException $e) {
            Log::error(sprintf('sendUnsignedSpectrePost: Could not get body from SpectreRequest::POST result: %s', $e->getMessage()));
            $body = '{}';
        }

        $statusCode                 = $res->getStatusCode();
        $responseHeaders            = $res->getHeaders();

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ImporterHttpException($e->getMessage());
        }
        $json['ResponseHeaders']    = $responseHeaders;
        $json['ResponseStatusCode'] = $statusCode;

        return $json;
    }

    /**
     * @throws ImporterHttpException
     * @throws ImporterErrorException
     */
    protected function sendUnsignedSpectrePut(array $data): array
    {
        if ('' === $this->url) {
            throw new ImporterErrorException('No Spectre server defined');
        }
        $fullUrl                    = sprintf('%s/%s', $this->getBase(), $this->getUrl());
        $headers                    = $this->getDefaultHeaders();
        $opts                       = ['headers' => $headers];
        $body                       = null;

        try {
            $body = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::error($e->getMessage());
        }
        if ('{}' !== (string) $body) {
            $opts['body'] = $body;
        }

        // Log::debug('Final body + headers for spectre UNsigned PUT request:', $opts);
        try {
            $client = $this->getClient();
            $res    = $client->request('PUT', $fullUrl, $opts);
        } catch (GuzzleException|RequestException $e) {
            // get response.
            $response = $e->getResponse();
            if (null !== $response && 406 === $response->getStatusCode()) {
                // ignore it, just log it.
                $statusCode                 = $response->getStatusCode();
                $responseHeaders            = $response->getHeaders();
                $json                       = [];

                try {
                    $json = json_decode((string) $e->getResponse()->getBody(), true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    Log::error($e->getMessage());
                }
                $json['ResponseHeaders']    = $responseHeaders;
                $json['ResponseStatusCode'] = $statusCode;

                return $json;
            }
            Log::error($e->getMessage());
            if (null !== $response) {
                Log::error((string) $e->getResponse()->getBody());
            }

            throw new ImporterHttpException(sprintf('Request Exception: %s', $e->getMessage()));
        }

        try {
            $body = $res->getBody()->getContents();
        } catch (\RuntimeException $e) {
            Log::error(sprintf('sendUnsignedSpectrePut: Could not get body from SpectreRequest::POST result: %s', $e->getMessage()));
            $body = '{}';
        }

        $statusCode                 = $res->getStatusCode();
        $responseHeaders            = $res->getHeaders();

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ImporterHttpException($e->getMessage());
        }
        $json['ResponseHeaders']    = $responseHeaders;
        $json['ResponseStatusCode'] = $statusCode;

        return $json;
    }
}
