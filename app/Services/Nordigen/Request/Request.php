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
use App\Exceptions\RateLimitException;
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
     * @throws RateLimitException
     */
    protected function authenticatedGet(): array
    {
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());

        if (0 !== count($this->parameters)) {
            $fullUrl = sprintf('%s?%s', $fullUrl, http_build_query($this->parameters));
        }
        app('log')->debug(sprintf('authenticatedGet(%s)', $fullUrl));
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
                        'user-agent'    => sprintf('Firefly III Nordigen importer / %s / %s', config('importer.version'), config('auth.line_b')),
                    ],
                ]
            );
        } catch (ClientException|GuzzleException|TransferException $e) {
            $statusCode      = $e->getCode();
            if (429 === $statusCode) {
                app('log')->debug(sprintf('Ran into exception: %s', get_class($e)));
                $this->logRateLimitHeaders($e->getResponse());
                $this->handleRateLimit($fullUrl, $e);
                $this->pauseForRateLimit($e->getResponse());
                return [];
            }
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
            $json            = [];
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
        $this->pauseForRateLimit($res);

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
                    ],
                ]
            );
        } catch (ClientException $e) {
            // TODO error response, not an exception.
            throw new ImporterHttpException(sprintf('AuthenticatedJsonPost: %s', $e->getMessage()), 0, $e);
        }
        $body    = (string) $res->getBody();
        $this->logRateLimitHeaders($res);
        $this->pauseForRateLimit($res);

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
        $headers = $res->getHeaders();
        if (array_key_exists('http_x_ratelimit_limit', $headers)) {
            app('log')->debug(sprintf('Rate limit: %s', $headers['http_x_ratelimit_limit'][0]));
        }
        if (array_key_exists('http_x_ratelimit_remaining', $headers)) {
            app('log')->debug(sprintf('Rate limit remaining: %s', $headers['http_x_ratelimit_remaining'][0]));
        }
        if (array_key_exists('http_x_ratelimit_reset', $headers)) {
            app('log')->debug(sprintf('Rate limit reset: %s', $headers['http_x_ratelimit_reset'][0]));
        }

        if (array_key_exists('http_x_ratelimit_account_success_limit', $headers)) {
            app('log')->debug(sprintf('Account success rate limit: %s', $headers['http_x_ratelimit_account_success_limit'][0]));
        }
        if (array_key_exists('http_x_ratelimit_account_success_remaining', $headers)) {
            app('log')->debug(sprintf('Account success rate limit remaining: %s', $headers['http_x_ratelimit_account_success_remaining'][0]));
        }
        if (array_key_exists('http_x_ratelimit_account_success_reset', $headers)) {
            app('log')->debug(sprintf('Account success rate limit reset: %s', $headers['http_x_ratelimit_account_success_reset'][0]));
        }

    }

    /**
     * @param ResponseInterface $res
     *
     * @return void
     * Header: http_x_ratelimit_limit: 100
     * Header: http_x_ratelimit_remaining: 92
     * Header: http_x_ratelimit_reset: 1
     * Header: http_x_ratelimit_account_success_limit: 10
     * Header: http_x_ratelimit_account_success_remaining: 5
     * Header: http_x_ratelimit_account_success_reset: 5242
     * @throws RateLimitException
     */
    private function pauseForRateLimit(ResponseInterface $res): void
    {
        app('log')->debug('Now in pauseForRateLimit');
        $headers = $res->getHeaders();

        // first the normal rate limit:
        $remaining   = (int) ($headers['http_x_ratelimit_remaining'][0] ?? 1000);
        $reset       = (int) ($headers['http_x_ratelimit_reset'][0] ?? 1);
        $resetString = $this->formatTime($reset);
        if ($remaining >= 10) {
            app('log')->debug(sprintf('Rate limit: %d requests remaining, and %s before the limit resets.', $remaining, $resetString));
        }
        if ($remaining < 10 && $remaining >= 5) {
            app('log')->info(sprintf('Rate limit: %d requests remaining, and %s before the limit resets.', $remaining, $resetString));
        }
        if ($remaining < 5 && $remaining >= 3) {
            app('log')->warning(sprintf('Rate limit: %d requests remaining, and %s before the limit resets.', $remaining, $resetString));
        }
        if ($remaining < 3 && $remaining >= 1) {
            app('log')->error(sprintf('Rate limit: %d requests remaining, and %d before the limit resets.', $remaining, $resetString));
        }
        if ($remaining < 1) {
            app('log')->critical(sprintf('Rate limit: %d requests remaining, and %s before the limit resets.', $remaining, $resetString));
            if (true === config('nordigen.exit_for_rate_limit')) {
                throw new RateLimitException(sprintf('Rate limit reached: %d requests left and %s before the limit resets.', $remaining, $resetString));
            }
            if (false === config('nordigen.exit_for_rate_limit')) {
                app('log')->info(sprintf('Rate limit reached, sleep %s for reset.', $resetString));
                sleep($reset + 1);
            }
        }

        // then the account success rate limit:
        $remaining   = (int) ($headers['http_x_ratelimit_account_success_remaining'][0] ?? 1000);
        $reset       = (int) ($headers['http_x_ratelimit_account_success_reset'][0] ?? 1);
        $resetString = $this->formatTime($reset);
        if ($remaining >= 10) {
            app('log')->debug(sprintf('Account success rate limit: %d requests remaining, and %s before the limit resets.', $remaining, $resetString));
        }
        if ($remaining < 10 && $remaining >= 5) {
            app('log')->info(sprintf('Account success rate limit: %d requests remaining, and %s before the limit resets.', $remaining, $resetString));
        }
        if ($remaining < 5 && $remaining >= 3) {
            app('log')->warning(sprintf('Account success rate limit: %d requests remaining, and %s before the limit resets.', $remaining, $resetString));
        }
        if ($remaining < 3 && $remaining >= 1) {
            app('log')->error(sprintf('Account success rate limit: %d requests remaining, and %d before the limit resets.', $remaining, $resetString));
        }
        if ($remaining < 1) {
            app('log')->critical(sprintf('Account success rate limit: %d requests remaining, and %s before the limit resets.', $remaining, $resetString));
            if (true === config('nordigen.exit_for_rate_limit')) {
                throw new RateLimitException(sprintf('Account success rate limit reached: %d requests left and %s before the limit resets.', $remaining, $resetString));
            }
            if (false === config('nordigen.exit_for_rate_limit')) {
                app('log')->info(sprintf('Account success rate limit reached, try to sleep %s for reset.', $resetString));
                if ($reset > 300) {
                    app('log')->warning('Refuse to sleep for more than 5 minutes, throw exception instead.');

                    throw new RateLimitException(sprintf('Account success rate limit reached: %d requests left and %s before the limit resets.', $remaining, $resetString));
                }
                sleep($reset + 1);
            }
        }
    }

    private function formatTime(int $reset): string
    {
        $return  = '';
        $hours   = floor($reset / 3600);
        if ($hours > 0) {
            $return .= sprintf('%dh', $hours);
        }
        $reset -= ($hours * 3600);
        $minutes = floor($reset / 60);
        if ($minutes > 0) {
            $return .= sprintf('%dm', $minutes);
        }
        $reset -= ($minutes * 60);
        $seconds = $reset % 60;
        if ($seconds > 0) {
            $return .= sprintf('%ds', $seconds);
        }

        return $return;
    }

    private function handleRateLimit(string $url, ClientException $e): void
    {
        app('log')->debug('Now in handleRateLimit');
        // if it's an account details request, we ignore the error for now. Can do without this information.
        if (str_contains($url, 'accounts') && str_contains($url, 'details')) {
            app('log')->debug('Its about account details');
            app('log')->warning('Rate limit reached on a request about account details. The data importer can continue.');
            $body = (string) $e->getResponse()->getBody();
            if (json_validate($body)) {
                $json        = json_decode($body, true);
                $message     = $json['detail'] ?? '';
                $re          = '/[1-9][0-9]+ seconds/m';
                preg_match_all($re, $message, $matches, PREG_SET_ORDER, 0);
                $string      = $matches[0][0] ?? '';
                $secondsLeft = (int) trim(str_replace(' seconds', '', $string));
                app('log')->warning(sprintf('Wait time until rate limit resets: %s', $this->formatTime($secondsLeft)));
            }
            return;
        }
        // if it's an account transactions request, we cannot ignore it and MUST stop.
        if (str_contains($url, 'accounts') && str_contains($url, 'transactions')) {
            app('log')->debug('Its about account transactions');
            app('log')->warning('Rate limit reached on a request about account transactions. The data importer CANNOT continue.');
            $body = (string) $e->getResponse()->getBody();
            if (json_validate($body)) {
                $json    = json_decode($body, true);
                $message = $json['detail'] ?? '';
                $re      = '/[1-9][0-9]+ seconds/m';
                preg_match_all($re, $message, $matches, PREG_SET_ORDER, 0);
                $string      = $matches[0][0] ?? '';
                $secondsLeft = (int) trim(str_replace(' seconds', '', $string));
                app('log')->warning(sprintf('Wait time until rate limit resets: %s', $this->formatTime($secondsLeft)));
            }
        }
    }
}
