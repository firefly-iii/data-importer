<?php

/*
 * Request.php
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

namespace App\Services\Nordigen\Request;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Exceptions\RateLimitException;
use App\Services\Shared\Response\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use JsonException;

/**
 * Class Request
 */
abstract class Request
{
    private string $base;
    private array  $parameters;
    private float  $timeOut = 3.14;

    private string $token;
    private string $url;

    private int $remaining  = -1;
    private int $reset      = -1;

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
        Log::debug('Request parameters will be set to: ', $parameters);
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
     * @throws RateLimitException
     */
    protected function authenticatedGet(): array
    {
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());

        if (0 !== count($this->parameters)) {
            $fullUrl = sprintf('%s?%s', $fullUrl, http_build_query($this->parameters));
        }
        Log::debug(sprintf('authenticatedGet(%s)', $fullUrl));
        $client  = $this->getClient();
        $body    = null;

        try {
            $res = $client->request(
                'GET',
                $fullUrl,
                [
                    'headers' => [
                        'Accept'        => 'application/json',
                        'Content-Type'  => 'application/json',
                        'Authorization' => sprintf('Bearer %s', $this->getToken()),
                        'User-Agent'    => sprintf('FF3-data-importer/%s (%s)', config('importer.version'), config('importer.line_a')),
                    ],
                ]
            );
        } catch (ClientException|GuzzleException|TransferException $e) {
            $statusCode      = $e->getCode();
            if (429 === $statusCode) {
                Log::debug(sprintf('Ran into exception: %s', $e::class));
                $this->logRateLimitHeaders($e->getResponse(), true);
                // $this->reportRateLimit($fullUrl, $e);
                $this->pauseForRateLimit($e->getResponse(), true);

                return [];
            }
            Log::error(sprintf('Original error: %s: %s', $e::class, $e->getMessage()));

            // crash but there is a response, log it.
            if (method_exists($e, 'getResponse') && method_exists($e, 'hasResponse') && $e->hasResponse()) {
                $response = $e->getResponse();
                Log::error(sprintf('%s', $response->getBody()->getContents()));
            }

            // if no response, parse as normal error response
            if (method_exists($e, 'hasResponse') && !$e->hasResponse()) {
                throw new ImporterHttpException(sprintf('Exception: %s', $e->getMessage()), 0, $e);
            }

            // if app can get response, parse it.
            $json            = [];
            if (method_exists($e, 'getResponse')) {
                $body = (string) $e->getResponse()->getBody();
                $json = json_decode($body, true) ?? [];
            }
            if (array_key_exists('summary', $json) && str_contains((string) $json['summary'], 'expired')) {
                $exception       = new AgreementExpiredException();
                $exception->json = $json;

                throw $exception;
            }

            // if status code is 503, the account does not exist.
            $exception       = new ImporterErrorException(sprintf('%s: %s', $e::class, $e->getMessage()), 0, $e);
            $exception->json = $json;

            throw $exception;
        }

        $this->logRateLimitHeaders($res, false);
        $this->pauseForRateLimit($res, false);

        if (200 !== $res->getStatusCode()) {
            // return body, class must handle this
            Log::error(sprintf('[1] Status code is %d', $res->getStatusCode()));

            $body = (string) $res->getBody();
        }
        $body ??= (string) $res->getBody();

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
            throw new ImporterHttpException(sprintf('Body is empty. [2] Status code is %d.', $res->getStatusCode()));
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
        Log::debug(sprintf('Now at %s', __METHOD__));
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());

        if (0 !== count($this->parameters)) {
            $fullUrl = sprintf('%s?%s', $fullUrl, http_build_query($this->parameters));
        }

        $client  = $this->getClient();

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
                        'User-Agent'    => sprintf('FF3-data-importer/%s (%s)', config('importer.version'), config('importer.line_e')),
                    ],
                ]
            );
        } catch (ClientException $e) {
            // TODO error response, not an exception.
            throw new ImporterHttpException(sprintf('AuthenticatedJsonPost: %s', $e->getMessage()), 0, $e);
        }
        $body    = (string) $res->getBody();
        $this->logRateLimitHeaders($res, false);
        $this->pauseForRateLimit($res, false);

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // TODO error response, not an exception.
            throw new ImporterHttpException(sprintf('AuthenticatedJsonPost JSON: %s', $e->getMessage()), 0, $e);
        }

        return $json;
    }

    private function logRateLimitHeaders(ResponseInterface $res, bool $fromErrorSituation): void
    {
        $headers = $res->getHeaders();
        $method  = $fromErrorSituation ? 'error' : 'debug';
        if (array_key_exists('http_x_ratelimit_limit', $headers)) {
            Log::{$method}(sprintf('Rate limit: %s', trim(implode(' ', $headers['http_x_ratelimit_limit']))));
        }
        if (array_key_exists('http_x_ratelimit_remaining', $headers)) {
            Log::{$method}(sprintf('Rate limit remaining: %s', trim(implode(' ', $headers['http_x_ratelimit_remaining']))));
        }
        if (array_key_exists('http_x_ratelimit_reset', $headers)) {
            Log::{$method}(sprintf('Rate limit reset: %s', trim(implode(' ', $headers['http_x_ratelimit_reset']))));
        }

        if (array_key_exists('http_x_ratelimit_account_success_limit', $headers)) {
            Log::{$method}(sprintf('Account success rate limit: %s', trim(implode(' ', $headers['http_x_ratelimit_account_success_limit']))));
        }
        if (array_key_exists('http_x_ratelimit_account_success_remaining', $headers)) {
            Log::{$method}(sprintf('Account success rate limit remaining: %s', trim(implode(' ', $headers['http_x_ratelimit_account_success_remaining']))));
        }
        if (array_key_exists('http_x_ratelimit_account_success_reset', $headers)) {
            Log::{$method}(sprintf('Account success rate limit reset: %s', trim(implode(' ', $headers['http_x_ratelimit_account_success_reset']))));
        }
    }

    /**
     * @throws RateLimitException
     */
    private function pauseForRateLimit(ResponseInterface $res, bool $fromErrorSituation): void
    {
        $method      = $fromErrorSituation ? 'error' : 'debug';
        Log::{$method}(sprintf('[%s] Now in pauseForRateLimit', config('importer.version')));
        $headers     = $res->getHeaders();

        // raw header values for debugging:
//        Log::debug(sprintf('http_x_ratelimit_remaining: %s', json_encode($headers['http_x_ratelimit_remaining'] ?? false)));
//        Log::debug(sprintf('http_x_ratelimit_reset: %s', json_encode($headers['http_x_ratelimit_reset'] ?? false)));
//        Log::debug(sprintf('http_x_ratelimit_account_success_remaining: %s', json_encode($headers['http_x_ratelimit_account_success_remaining'] ?? false)));
//        Log::debug(sprintf('http_x_ratelimit_account_success_reset: %s', json_encode($headers['http_x_ratelimit_account_success_reset'] ?? false)));

        // first the normal rate limit:
        $remaining   = (int) ($headers['http_x_ratelimit_remaining'][0] ?? -2);
        $reset       = (int) ($headers['http_x_ratelimit_reset'][0] ?? -2);
        $this->reportAndPause('Rate limit', $remaining, $reset, $fromErrorSituation);

        // then the account success rate limit:
        $remaining   = (int) ($headers['http_x_ratelimit_account_success_remaining'][0] ?? -2);
        $reset       = (int) ($headers['http_x_ratelimit_account_success_reset'][0] ?? -2);

        // save the remaining info in the object.
        $this->reset = $reset;
        if ($remaining > -1) { // zero or more.
            Log::{$method}('Save the account success limits? YES');
            $this->remaining = $remaining;
        }
        if ($remaining < 0) {  // less than zero.
            Log::{$method}('Save the account success limits? NO');
        }

        $this->reportAndPause('Account success limit', $remaining, $reset, $fromErrorSituation);
    }

    public static function formatTime(int $reset): string
    {
        $return  = '';
        if ($reset < 0) {
            Log::warning('The reset time is negative!');
            $return = '-';
            $reset  = abs($reset);
        }

        // days:
        $days    = floor($reset / 86400);
        if ($days > 0) {
            $return .= sprintf('%dd', $days);
        }
        $reset   -= ($days * 86400);

        $hours   = floor($reset / 3600);
        if ($hours > 0) {
            $return .= sprintf('%dh', $hours);
        }
        $reset   -= ($hours * 3600);
        $minutes = floor($reset / 60);
        if ($minutes > 0) {
            $return .= sprintf('%dm', $minutes);
        }
        $reset   -= ($minutes * 60);
        $seconds = $reset % 60;
        if ($seconds > 0) {
            $return .= sprintf('%ds', $seconds);
        }

        return $return;
    }

    private function reportAndPause(string $type, int $remaining, int $reset, bool $fromErrorSituation): void
    {
        if ($remaining < 0) {
            // no need to report:
            return;
        }
        $resetString = self::formatTime($reset);
        if ($remaining >= 5) {
            Log::debug(sprintf('%s: %d requests left, and %s before the limit resets.', $type, $remaining, $resetString));

            return;
        }
        if ($remaining >= 1) {
            Log::warning(sprintf('%s: %d requests remaining, it will take %s for the limit to reset.', $type, $remaining, $resetString));

            return;
        }

        // extra message if error.
        if ($reset > 1) {
            Log::error(sprintf('%s: Have zero requests left!', $type));
        }

        // do exit?
        if (true === config('nordigen.exit_for_rate_limit') && $fromErrorSituation) {
            throw new RateLimitException(sprintf('%s reached: there are %d requests left and %s before the limit resets.', $type, $remaining, $resetString));
        }

        // no exit. Do sleep?
        if ($reset < 300 && $reset > 0) {
            Log::info(sprintf('%s reached, sleep %s for reset.', $type, $resetString));
            sleep($reset + 1);

            return;
        }
        if ($reset >= 300) {
            Log::error(sprintf('%s: Refuse to sleep for %s, throw exception instead.', $type, $resetString));
        }
        if ($reset < 0) {
            Log::error(sprintf('%s: Reset time is a negative number (%d = %s), this is an issue.', $type, $reset, $resetString));
        }
        if ($fromErrorSituation) {
            throw new RateLimitException(sprintf('%s reached: %d requests remaining, and %s before the limit resets.', $type, $remaining, $resetString));
        }
    }

    public function getRemaining(): int
    {
        return $this->remaining;
    }

    public function getReset(): int
    {
        return $this->reset;
    }
}
