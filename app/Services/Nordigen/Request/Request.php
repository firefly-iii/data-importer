<?php
/*
 * Request.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Nordigen importer
 * (https://github.com/firefly-iii/nordigen-importer).
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

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Shared\Response\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use JsonException;
use Log;

/**
 * Class Request
 */
abstract class Request
{
    private string $base;
    private array  $body;
    private array  $parameters;
    /** @var string */
    private string $token;
    private float  $timeOut = 3.14;
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
        Log::debug('Request parameters will be set to: ', $parameters);
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
     * @throws ImporterErrorException
     * @throws ImporterHttpException
     */
    protected function authenticatedGet(): array
    {
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());

        if (null !== $this->parameters) {
            $fullUrl = sprintf('%s?%s', $fullUrl, http_build_query($this->parameters));
        }
        Log::debug(sprintf('authenticatedGet(%s)', $fullUrl));
        $client = $this->getClient();
        $body   = null;
        try {
            $res = $client->request(
                'GET', $fullUrl, [
                         'headers' => [
                             'Accept'        => 'application/json',
                             'Content-Type'  => 'application/json',
                             'Authorization' => sprintf('Bearer %s', $this->getToken()),
                             'user-agent'    => sprintf('Firefly III Nordigen importer / %s / %s', config('importer.version'), config('auth.line_b')),
                         ],
                     ]
            );
        } catch (TransferException | GuzzleException $e) {
            Log::error(sprintf('TransferException: %s', $e->getMessage()));
            // if response, parse as error response
            if (!$e->hasResponse()) {
                throw new ImporterHttpException(sprintf('Exception: %s', $e->getMessage()), 0, $e);
            }
            $body = (string) $e->getResponse()->getBody();
            $json = [];
            try {
                $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $ee) {
                Log::error(sprintf('Could not decode error: %s', $ee->getMessage()));
                Log::error($body);
                //throw new ImporterHttpException(sprintf('Could not decode JSON: %s', $e->getMessage()), 0, $e);
            }
            $exception       = new ImporterErrorException($e->getMessage(), 0, $e);
            $exception->json = $json;
            throw $exception;
        }
        if (null !== $res && 200 !== $res->getStatusCode()) {
            // return body, class must handle this
            Log::error(sprintf('Status code is %d', $res->getStatusCode()));

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
        Log::debug('Return JSON result of authenticatedGet');

        return $json;
    }

    /**
     * @return array
     * @throws ImporterHttpException
     */
    protected function authenticatedJsonPost(array $json): array
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());

        if (null !== $this->parameters) {
            $fullUrl = sprintf('%s?%s', $fullUrl, http_build_query($this->parameters));
        }

        $client = $this->getClient();
        try {
            $res = $client->request(
                'POST', $fullUrl, [
                          'json'    => $json,
                          'headers' => [
                              'Accept'        => 'application/json',
                              'Content-Type'  => 'application/json',
                              'Authorization' => sprintf('Bearer %s', $this->getToken()),
                          ],
                      ]
            );
        } catch (ClientException $e) {
            // TODO error response, not an exception.
            throw new ImporterHttpException(sprintf('AuthenticatedJsonPost: %s', $e->getMessage()), 0, $e);
        }
        $body = (string) $res->getBody();

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // TODO error response, not an exception.
            throw new ImporterHttpException(sprintf('AuthenticatedJsonPost JSON: %s', $e->getMessage()), 0, $e);
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
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }


}
