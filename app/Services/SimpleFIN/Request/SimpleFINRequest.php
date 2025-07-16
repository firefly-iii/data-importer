<?php

/*
 * SimpleFINRequest.php
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

namespace App\Services\SimpleFIN\Request;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Shared\Response\ResponseInterface as SharedResponseInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

/**
 * Class SimpleFINRequest
 */
abstract class SimpleFINRequest
{
    private string $apiUrl;
    private string $token;
    private array $parameters   = [];
    private float $timeOut;

    private string $accessToken = '';

    /**
     * @throws ImporterHttpException
     */
    abstract public function get(): SharedResponseInterface;

    public function setApiUrl(string $apiUrl): void
    {
        $this->apiUrl = rtrim($apiUrl, '/');
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function setParameters(array $parameters): void
    {
        Log::debug('SimpleFIN request parameters set to: ', $parameters);
        $this->parameters = $parameters;
    }

    public function setTimeOut(float $timeOut): void
    {
        $this->timeOut = $timeOut;
    }

    protected function authenticatedGet(string $endpoint): ResponseInterface
    {
        Log::debug(sprintf('SimpleFIN authenticated GET to %s%s', $this->accessToken, $endpoint));

        $client  = new Client();
        $fullUrl = sprintf('%s%s', $this->accessToken, $endpoint);

        $options = [
            'timeout' => $this->timeOut,
            'headers' => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => sprintf('FF3-data-importer/%s (%s)', config('importer.version'), config('importer.line_c'))
            ],
        ];

        if (count($this->parameters) > 0) {
            $options['query'] = $this->parameters;
        }
        Log::debug('Options', $options);

        try {
            $response = $client->get($fullUrl, $options);
        } catch (ClientException $e) {
            Log::error(sprintf('SimpleFIN ClientException: %s', $e->getMessage()));
            $this->handleClientException($e);

            throw new ImporterHttpException($e->getMessage(), $e->getCode(), $e);
        } catch (ServerException $e) {
            Log::error(sprintf('SimpleFIN ServerException: %s', $e->getMessage()));

            throw new ImporterHttpException($e->getMessage(), $e->getCode(), $e);
        } catch (GuzzleException $e) {
            Log::error(sprintf('SimpleFIN GuzzleException: %s', $e->getMessage()));

            throw new ImporterHttpException($e->getMessage(), $e->getCode(), $e);
        }

        return $response;
    }

    private function handleClientException(ClientException $e): void
    {
        $statusCode = $e->getResponse()->getStatusCode();
        $body       = (string) $e->getResponse()->getBody();

        Log::error(sprintf('SimpleFIN HTTP %d error: %s', $statusCode, $body));

        switch ($statusCode) {
            case 401:
                throw new ImporterErrorException('Invalid SimpleFIN token or authentication failed');

            case 403:
                throw new ImporterErrorException('Access denied to SimpleFIN resource');

            case 404:
                throw new ImporterErrorException('SimpleFIN resource not found');

            case 429:
                throw new ImporterErrorException('SimpleFIN rate limit exceeded');

            default:
                throw new ImporterErrorException(sprintf('SimpleFIN API error (HTTP %d): %s', $statusCode, $body));
        }
    }

    protected function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    protected function getToken(): string
    {
        return $this->token;
    }

    protected function getParameters(): array
    {
        return $this->parameters;
    }

    protected function getTimeOut(): float
    {
        return $this->timeOut;
    }

    public function setAccessToken(string $accessToken): void
    {
        Log::debug(sprintf('Access token is now: %s', $accessToken));
        $this->accessToken = $accessToken;
    }
}
