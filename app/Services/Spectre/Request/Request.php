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

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Shared\Response\Response;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use JsonException;
use RuntimeException;

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
     * @return Response
     * @throws ImporterHttpException
     */
    abstract public function get(): Response;

    /**
     * @return Response
     * @throws ImporterHttpException
     */
    abstract public function post(): Response;

    /**
     * @return Response
     * @throws ImporterHttpException
     */
    abstract public function put(): Response;

    /**
     * @param array $body
     */
    public function setBody(array $body): void
    {
        $this->body = $body;
    }

    /**
     * @param array $parameters
     */
    public function setParameters(array $parameters): void
    {
        app('log')->debug('setParameters', $parameters);
        $this->parameters = $parameters;
    }

    /**
     * @param float $timeOut
     */
    public function setTimeOut(float $timeOut): void
    {
        $this->timeOut = $timeOut;
    }

    /**
     * @return array
     * @throws ImporterHttpException
     * @throws ImporterErrorException
     */
    protected function authenticatedGet(): array
    {
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());

        if (null !== $this->parameters) {
            $fullUrl = sprintf('%s?%s', $fullUrl, http_build_query($this->parameters));
        }
        $client = $this->getClient();
        $res    = null;
        $body   = null;
        $json   = null;
        try {
            $res = $client->request(
                'GET', $fullUrl, [
                         'headers' => [
                             'Accept'       => 'application/json',
                             'Content-Type' => 'application/json',
                             'App-id'       => $this->getAppId(),
                             'Secret'       => $this->getSecret(),
                         ],
                     ]
            );
        } catch (TransferException $e) {
            app('log')->error(sprintf('TransferException: %s', $e->getMessage()));
            // if response, parse as error response.re
            if (!$e->hasResponse()) {
                throw new ImporterHttpException(sprintf('Exception: %s', $e->getMessage()));
            }
            $body = (string) $e->getResponse()->getBody();
            $json = [];
            try {
                $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                app('log')->error(sprintf('Could not decode error: %s', $e->getMessage()));
            }

            $exception       = new ImporterErrorException;
            $exception->json = $json;
            throw $exception;
        }
        if (null !== $res && 200 !== $res->getStatusCode()) {
            // return body, class must handle this
            app('log')->error(sprintf('Status code is %d', $res->getStatusCode()));

            $body = (string) $res->getBody();
        }
        $body = $body ?? (string) $res->getBody();

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ImporterHttpException(
                sprintf(
                    'Could not decode JSON (%s). Error[%d] is: %s. Response: %s',
                    $fullUrl,
                    $res ? $res->getStatusCode() : 0,
                    $e->getMessage(),
                    $body
                )
            );
        }

        if (null === $json) {
            throw new ImporterHttpException(sprintf('Body is empty. Status code is %d.', $res->getStatusCode()));
        }

        return $json;
    }

    /**
     * @return string
     */
    public function getBase(): string
    {
        return $this->base;
    }

    /**
     * @param string $base
     */
    public function setBase(string $base): void
    {
        $this->base = $base;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return Client
     */
    private function getClient(): Client
    {
        // config here

        return new Client(
            [
                'connect_timeout' => $this->timeOut,
            ]
        );
    }

    /**
     * @return string
     */
    public function getAppId(): string
    {
        return $this->appId;
    }

    /**
     * @param string $appId
     */
    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    /**
     * @return string
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * @param string $secret
     */
    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }

    /**
     * @param array $data
     *
     * @return array
     *
     * @throws ImporterHttpException
     * @throws ImporterErrorException
     */
    protected function sendSignedSpectrePost(array $data): array
    {
        if ('' === $this->url) {
            throw new ImporterErrorException('No Spectre server defined');
        }
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());
        $headers = $this->getDefaultHeaders();
        try {
            $body = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ImporterErrorException($e->getMessage());
        }

        app('log')->debug('Final headers for spectre signed POST request:', $headers);
        try {
            $client = $this->getClient();
            $res    = $client->request('POST', $fullUrl, ['headers' => $headers, 'body' => $body]);
        } catch (GuzzleException | Exception $e) {
            throw new ImporterHttpException(sprintf('Guzzle Exception: %s', $e->getMessage()));
        }

        try {
            $body = $res->getBody()->getContents();
        } catch (RuntimeException $e) {
            app('log')->error(sprintf('Could not get body from SpectreRequest::POST result: %s', $e->getMessage()));
            $body = '{}';
        }

        $statusCode      = $res->getStatusCode();
        $responseHeaders = $res->getHeaders();


        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ImporterHttpException($e->getMessage());
        }
        $json['ResponseHeaders']    = $responseHeaders;
        $json['ResponseStatusCode'] = $statusCode;

        return $json;
    }

    /**
     * @return array
     */
    protected function getDefaultHeaders(): array
    {
        $userAgent       = sprintf('FireflyIII Spectre v%s', config('spectre.version'));
        $this->expiresAt = time() + 180;

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
     * @param array $data
     *
     * @return array
     *
     * @throws ImporterHttpException
     * @throws ImporterErrorException
     */
    protected function sendUnsignedSpectrePost(array $data): array
    {
        if ('' === $this->url) {
            throw new ImporterErrorException('No Spectre server defined');
        }
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());
        $headers = $this->getDefaultHeaders();
        $opts    = ['headers' => $headers];
        $body    = null;
        try {
            $body = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            app('log')->error($e->getMessage());
        }
        if ('{}' !== (string) $body) {
            $opts['body'] = $body;
        }

        app('log')->debug('Final headers for spectre UNsigned POST request:', $headers);
        try {
            $client = $this->getClient();
            $res    = $client->request('POST', $fullUrl, $opts);
        } catch (GuzzleException | Exception $e) {
            app('log')->error($e->getMessage());
            throw new ImporterHttpException(sprintf('Guzzle Exception: %s', $e->getMessage()));
        }

        try {
            $body = $res->getBody()->getContents();
        } catch (RuntimeException $e) {
            app('log')->error(sprintf('Could not get body from SpectreRequest::POST result: %s', $e->getMessage()));
            $body = '{}';
        }

        $statusCode      = $res->getStatusCode();
        $responseHeaders = $res->getHeaders();


        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ImporterHttpException($e->getMessage());
        }
        $json['ResponseHeaders']    = $responseHeaders;
        $json['ResponseStatusCode'] = $statusCode;

        return $json;
    }

    /**
     * @param array $data
     *
     * @return array
     *
     * @throws ImporterHttpException
     * @throws ImporterErrorException
     */
    protected function sendUnsignedSpectrePut(array $data): array
    {
        if ('' === $this->url) {
            throw new ImporterErrorException('No Spectre server defined');
        }
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());
        $headers = $this->getDefaultHeaders();
        $opts    = ['headers' => $headers];
        $body    = null;

        try {
            $body = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            app('log')->error($e->getMessage());
        }
        if ('{}' !== (string) $body) {
            $opts['body'] = $body;
        }
        //app('log')->debug('Final body + headers for spectre UNsigned PUT request:', $opts);
        try {
            $client = $this->getClient();
            $res    = $client->request('PUT', $fullUrl, $opts);
        } catch (RequestException | GuzzleException $e) {
            // get response.
            $response = $e->getResponse();
            if (null !== $response && 406 === $response->getStatusCode()) {
                // ignore it, just log it.
                $statusCode      = $response->getStatusCode();
                $responseHeaders = $response->getHeaders();
                $json            = [];
                try {
                    $json = json_decode((string) $e->getResponse()->getBody(), true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    app('log')->error($e->getMessage());
                }
                $json['ResponseHeaders']    = $responseHeaders;
                $json['ResponseStatusCode'] = $statusCode;

                return $json;
            }
            app('log')->error($e->getMessage());
            if (null !== $response) {
                app('log')->error((string) $e->getResponse()->getBody());
            }
            throw new ImporterHttpException(sprintf('Request Exception: %s', $e->getMessage()));
        }

        try {
            $body = $res->getBody()->getContents();
        } catch (RuntimeException $e) {
            app('log')->error(sprintf('Could not get body from SpectreRequest::POST result: %s', $e->getMessage()));
            $body = '{}';
        }

        $statusCode      = $res->getStatusCode();
        $responseHeaders = $res->getHeaders();


        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ImporterHttpException($e->getMessage());
        }
        $json['ResponseHeaders']    = $responseHeaders;
        $json['ResponseStatusCode'] = $statusCode;

        return $json;
    }
}
