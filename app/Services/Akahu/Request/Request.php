<?php


/*
 * Request.php
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

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Akahu\Authentication\SecretManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;

abstract class Request
{
    private string $appIdToken;
    private string $userAccessToken;
    private string $apiBaseUrl;
    private bool $debug;
    private array $queryParams      = [];
    private array $historicRequests = [];
    private HandlerStack $handlerStack;
    private float $timeOut          = 30.0;

    public function __construct()
    {
        $this->setDebug(false);
        $this->setAppIdToken(SecretManager::getAppIdToken());
        $this->setUserAccessToken(SecretManager::getUserAccessToken());
        $this->setApiBaseUrl(config('akahu.base_url'));

        if (config('app.debug')) {
            $this->createHandlerStack();
        }
    }

    final public function setAppIdToken(#[SensitiveParameter] string $token): void
    {
        $this->appIdToken = $token;
    }

    final public function setUserAccessToken(#[SensitiveParameter] string $token): void
    {
        $this->userAccessToken = $token;
    }

    final public function setApiBaseUrl(string $baseUrl): void
    {
        $this->apiBaseUrl = $baseUrl;
    }

    final public function setRequestTimeout(float $timeOut): void
    {
        $this->timeOut = $timeOut;
    }

    final public function setQueryParam(string $key, string $value): void
    {
        $this->queryParams[$key] = $value;
    }

    final public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    private function createHandlerStack(): void
    {
        $this->handlerStack = HandlerStack::create();
        $this->handlerStack->push(Middleware::history($this->historicRequests));
    }

    final public function getHistoricRequests(): array
    {
        return $this->historicRequests;
    }

    final protected function getClient(): Client
    {
        return new Client(['connect_timeout' => $this->timeOut, 'timeout' => $this->timeOut]);
    }

    final protected function authenticatedGet(string $apiPath): array
    {
        $apiUrl           = sprintf('%s/%s', $this->apiBaseUrl, $apiPath);

        $headers          = [
            'Accept'        => 'application/json',
            'User-Agent'    => sprintf('FF3-data-importer/%s', config('importer.version')),
            'Authorization' => sprintf('Bearer %s', $this->userAccessToken),
            'X-Akahu-Id'    => $this->appIdToken,
        ];

        $query            = $this->queryParams;
        $debug            = $this->debug;

        $opts             = ['headers' => $headers, 'query' => $query, 'debug' => $debug];

        $handlerStackName = [];

        if (config('app.debug')) {
            $opts['handler'] = $this->handlerStack;
        }

        $client           = $this->getClient();

        try {
            $response = $client->request('GET', $apiUrl, $opts);
        } catch (ConnectException|TooManyRedirectsException $e) {
            throw new ImporterErrorException(sprintf('Failed to connect to the Akahu api (%s): %s', $apiUrl, $e->getMessage()));
        } catch (BadResponseException $e) {
            throw new ImporterHttpException(
                sprintf('Akahu api returned an error response (%s): %s', $apiUrl, $e->getMessage()),
                $e->getResponse()->getStatusCode(),
                $e
            );
        }

        if (config('app.debug')) {
            $historicRequests = $this->getHistoricRequests();
            $lastRequest      = end($historicRequests)['request'];
            Log::debug(sprintf('Fetched from from endpoint "%s"', $lastRequest->getUri()));
        }

        $json             = json_decode((string) $response->getBody(), true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $msg = sprintf('Akahu api returned invalid json (%s). See logs for more details.', $apiUrl);

            Log::error($msg.' json: "'.$response->getBody().'"');

            throw new ImporterErrorException($msg);
        }

        return $json;
    }
}
