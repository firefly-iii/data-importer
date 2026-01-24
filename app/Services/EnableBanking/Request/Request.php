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

namespace App\Services\EnableBanking\Request;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\EnableBanking\JWTManager;
use App\Services\Shared\Response\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Class Request
 */
abstract class Request
{
    private string $base;
    private string $url;
    private array $parameters = [];
    private float $timeOut = 30.0;

    abstract public function get(): Response;

    abstract public function post(): Response;

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function setTimeOut(float $timeOut): void
    {
        $this->timeOut = $timeOut;
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

    protected function getClient(): Client
    {
        return new Client([
            'connect_timeout' => $this->timeOut,
            'timeout' => $this->timeOut,
        ]);
    }

    protected function getHeaders(): array
    {
        $token = JWTManager::generateToken();

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => sprintf('Bearer %s', $token),
            'User-Agent' => sprintf('FF3-data-importer/%s', config('importer.version')),
        ];
    }

    /**
     * @throws ImporterHttpException
     * @throws ImporterErrorException
     */
    protected function authenticatedGet(): array
    {
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());

        if (count($this->parameters) > 0) {
            $fullUrl = sprintf('%s?%s', $fullUrl, http_build_query($this->parameters));
        }

        Log::debug(sprintf('Enable Banking authenticatedGet(%s)', $fullUrl));

        $client = $this->getClient();

        try {
            $res = $client->request('GET', $fullUrl, [
                'headers' => $this->getHeaders(),
            ]);
        } catch (ClientException|GuzzleException $e) {
            Log::error(sprintf('Enable Banking API error: %s', $e->getMessage()));

            if (method_exists($e, 'getResponse') && method_exists($e, 'hasResponse') && $e->hasResponse()) {
                $body = (string) $e->getResponse()->getBody();
                Log::error(sprintf('Response body: %s', $body));
            }

            throw new ImporterHttpException(sprintf('Enable Banking API error: %s', $e->getMessage()), 0, $e);
        }

        $body = (string) $res->getBody();
        Log::debug(sprintf('Enable Banking raw response: %s', $body));

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ImporterHttpException(sprintf('Could not decode JSON: %s', $e->getMessage()));
        }

        Log::debug(sprintf('Enable Banking parsed response keys: %s', implode(', ', array_keys($json ?? []))));

        return $json ?? [];
    }

    /**
     * @throws ImporterHttpException
     */
    protected function authenticatedPost(array $data): array
    {
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());

        Log::debug(sprintf('Enable Banking authenticatedPost(%s)', $fullUrl));

        $client = $this->getClient();

        try {
            $res = $client->request('POST', $fullUrl, [
                'headers' => $this->getHeaders(),
                'json' => $data,
            ]);
        } catch (ClientException|GuzzleException $e) {
            Log::error(sprintf('Enable Banking API error: %s', $e->getMessage()));

            if (method_exists($e, 'getResponse') && method_exists($e, 'hasResponse') && $e->hasResponse()) {
                $body = (string) $e->getResponse()->getBody();
                Log::error(sprintf('Response body: %s', $body));
            }

            throw new ImporterHttpException(sprintf('Enable Banking API error: %s', $e->getMessage()), 0, $e);
        }

        $body = (string) $res->getBody();

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ImporterHttpException(sprintf('Could not decode JSON: %s', $e->getMessage()));
        }

        return $json ?? [];
    }
}
