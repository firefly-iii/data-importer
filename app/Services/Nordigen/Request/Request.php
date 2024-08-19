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

namespace App\Services\Nordigen\Request;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Shared\Response\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Request
 */
abstract class Request
{
    private string $base;
    private array  $body;
    private array  $parameters;
    private float  $timeOut = 3.14;

    private string $token;
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
        app('log')->debug('Request parameters will be set to: ', $parameters);
        $this->parameters = $parameters;
    }

    public function setTimeOut(float $timeOut): void
    {
        $this->timeOut = $timeOut;
    }

    /**
     * @throws ImporterErrorException
     * @throws ImporterHttpException
     * @throws AgreementExpiredException
     */
    protected function authenticatedGet(): array
    {
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());

        if (0 !== count($this->parameters)) {
            $fullUrl = sprintf('%s?%s', $fullUrl, http_build_query($this->parameters));
        }
        app('log')->debug(sprintf('authenticatedGet(%s)', $fullUrl));
        $client = $this->getClient();
        $body   = null;

        try {
            $res = $client->request(
                'GET',
                $fullUrl,
                [
                    'headers' => [
                        'Accept'        => 'application/json',
                        'Content-Type'  => 'application/json',
                        'Authorization' => sprintf('Bearer %s', $this->getToken()),
                        'user-agent'    => sprintf('Firefly III Nordigen importer / %s / %s', config('importer.version'), config('auth.line_b')),
                    ],
                ]
            );
        } catch (GuzzleException | TransferException $e) {
            app('log')->error(sprintf('%s: %s', get_class($e), $e->getMessage()));

            // crash but there is a response, log it.
            if (method_exists($e, 'getResponse') && method_exists($e, 'hasResponse') && $e->hasResponse()) {
                $response = $e->getResponse();
                app('log')->error(sprintf('%s', $response->getBody()->getContents()));
            }

            // if no response, parse as normal error response
            if (method_exists($e, 'hasResponse') && !$e->hasResponse()) {
                throw new ImporterHttpException(sprintf('Exception: %s', $e->getMessage()), 0, $e);
            }

            // if app can get response, parse it.
            $json = [];
            if (method_exists($e, 'getResponse')) {
                $body = (string) $e->getResponse()->getBody();
                $json = json_decode($body, true) ?? [];
            }
            if (array_key_exists('summary', $json) && str_ends_with($json['summary'], 'has expired')) {
                $exception       = new AgreementExpiredException();
                $exception->json = $json;

                throw $exception;
            }

            // if status code is 503, the account does not exist.
            $exception       = new ImporterErrorException(sprintf('%s: %s', get_class($e), $e->getMessage()), 0, $e);
            $exception->json = $json;

            throw $exception;
        }

        $this->logRateLimitHeaders($res);

        if (200 !== $res->getStatusCode()) {
            // return body, class must handle this
            app('log')->error(sprintf('[1] Status code is %d', $res->getStatusCode()));

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
            throw new ImporterHttpException(sprintf('Body is empty. [2] Status code is %d.', $res->getStatusCode()));
        }
        if (config('importer.log_return_json')) {
            app('log')->debug('JSON', $json);
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

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @throws GuzzleException
     * @throws ImporterHttpException
     */
    protected function authenticatedJsonPost(array $json): array
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());

        if (0 !== count($this->parameters)) {
            $fullUrl = sprintf('%s?%s', $fullUrl, http_build_query($this->parameters));
        }

        $client = $this->getClient();

        try {
            $res = $client->request(
                'POST',
                $fullUrl,
                [
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
        $this->logRateLimitHeaders($res);

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // TODO error response, not an exception.
            throw new ImporterHttpException(sprintf('AuthenticatedJsonPost JSON: %s', $e->getMessage()), 0, $e);
        }

        return $json;
    }

    private function logRateLimitHeaders(ResponseInterface $res): void
    {
        app('log')->debug('Now in logRateLimitHeaders');
        $ignore  = ['server', 'date', 'content-type', 'content-length', 'connection', 'vary', 'Content-Length', 'allow', 'cache-control', 'x-frame-options', 'content-language', 'x-c-uuid', 'x-u-uuid', 'x-content-type-options', 'referrer-policy', 'client-region', 'cf-ipcountry', 'strict-transport-security', 'Via', 'Alt-Svc'];
        $headers = $res->getHeaders();
        $count = 0;
        foreach ($headers as $header => $content) {
            if (in_array($header, $ignore, true)) {
                continue;
            }
            app('log')->debug(sprintf('Header: %s: %s', $header, $content[0] ?? '(empty)'));
            $count++;
        }
        // HTTP_X_RATELIMIT_LIMIT: Indicates the maximum number of allowed requests within the defined time window.
        // HTTP_X_RATELIMIT_REMAINING: Shows the number of remaining requests you can make in the current time window.
        // HTTP_X_RATELIMIT_RESET
        if (array_key_exists('x-ratelimit-limit', $headers)) {
            app('log')->debug(sprintf('Rate limit: %s', $headers['x-ratelimit-limit'][0]));
        }
        if (array_key_exists('x-ratelimit-remaining', $headers)) {
            app('log')->debug(sprintf('Rate limit remaining: %s', $headers['x-ratelimit-remaining'][0]));
        }
        if (array_key_exists('x-ratelimit-reset', $headers)) {
            app('log')->debug(sprintf('Rate limit reset: %s', $headers['x-ratelimit-reset'][0]));
        }

        // HTTP_X_RATELIMIT_ACCOUNT_SUCCESS_LIMIT: Indicates the maximum number of allowed requests within the defined time window.
        // HTTP_X_RATELIMIT_ACCOUNT_SUCCESS_REMAINING: Shows the number of remaining requests you can make in the current time window.
        // HTTP_X_RATELIMIT_ACCOUNT_SUCCESS_RESET: Provides the time remaining in the current window.
        if (array_key_exists('x-ratelimit-account-success-limit', $headers)) {
            app('log')->debug(sprintf('Account success rate limit: %s', $headers['x-ratelimit-limit'][0]));
        }
        if (array_key_exists('x-ratelimit-account-success-remaining', $headers)) {
            app('log')->debug(sprintf('Account success rate limit remaining: %s', $headers['x-ratelimit-remaining'][0]));
        }
        if (array_key_exists('x-ratelimit-account-success-reset', $headers)) {
            app('log')->debug(sprintf('Account success rate limit reset: %s', $headers['x-ratelimit-reset'][0]));
        }

        app('log')->debug(sprintf('Have %d header(s) to show.', $count));
    }

}
