<?php

/*
 * Request.php
 * Copyright (c) 2026 open-banking.io contribution to Firefly III (https://github.com/firefly-iii).
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

namespace App\Services\OpenBankingIo\Request;

use App\Exceptions\ImporterHttpException;
use App\Services\OpenBankingIo\Crypto\Envelope;
use App\Services\Shared\Response\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Log;
use JsonException;
use SensitiveParameter;

/**
 * Class Request
 */
abstract class Request
{
    /** Total request timeout, in seconds (guards against a hung/slow endpoint). */
    private const float TIMEOUT         = 60.0;

    private string $base                = '';
    private float $timeOut              = 3.14;
    private string $url                 = '';
    private string $apiKey              = '';
    private ?Envelope $envelope         = null;
    private ?HandlerStack $handlerStack = null;

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

    public function setTimeOut(float $timeOut): void
    {
        $this->timeOut = $timeOut;
    }

    /**
     * @return array<int|string, mixed>
     *
     * @throws GuzzleException
     * @throws ImporterHttpException
     */
    protected function authenticatedGet(): array
    {
        $fullUrl = sprintf('%s/%s', $this->getBase(), $this->getUrl());
        $client  = $this->getClient();
        $body    = null;

        try {
            $res = $client->request('GET', $fullUrl, ['headers' => [
                'Accept'     => 'application/json',
                'X-Api-Key'  => $this->apiKey,
                'User-Agent' => sprintf('FF3-data-importer/%s (open-banking.io)', config('importer.version')),
            ]]);
        } catch (TransferException $e) {
            Log::error(sprintf('open-banking.io TransferException: %s', $e->getMessage()));
            if (method_exists($e, 'hasResponse') && !$e->hasResponse()) {
                throw new ImporterHttpException(sprintf('Exception: %s', $e->getMessage()));
            }
            $body                  = method_exists($e, 'getResponse') && null !== $e->getResponse() ? (string) $e->getResponse()->getBody() : '';
            $exception             = new ImporterHttpException(sprintf('Transfer exception leads to error: %s', $body), 0, $e);
            $exception->statusCode = method_exists($e, 'getResponse') && null !== $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;

            throw $exception;
        }
        if (200 !== $res->getStatusCode()) {
            Log::error(sprintf('[obio] Status code is %d', $res->getStatusCode()));
            $body = (string) $res->getBody();
        }
        $body ??= (string) $res->getBody();

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ImporterHttpException(sprintf(
                'Could not decode JSON (%s). Error[%d] is: %s. Response: %s',
                $fullUrl,
                $res->getStatusCode(),
                $e->getMessage(),
                $body
            ));
        }

        if (null === $json) {
            throw new ImporterHttpException(sprintf('Body is empty. Status code is %d.', $res->getStatusCode()));
        }

        return $json;
    }

    public function getBase(): string
    {
        return $this->base;
    }

    public function setBase(string $base): void
    {
        $this->base = rtrim($base, '/');
        if ('' !== $this->base && !str_starts_with($this->base, 'https://')
            && !str_contains($this->base, '://localhost') && !str_contains($this->base, '://127.0.0.1')) {
            Log::warning(sprintf('open-banking.io: API base URL "%s" is not HTTPS; your API key would be sent in cleartext.', $this->base));
        }
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = ltrim($url, '/');
    }

    public function setApiKey(#[SensitiveParameter] string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setPrivateKey(#[SensitiveParameter] string $privateKeyPkcs8): void
    {
        // Only build the decryptor when a key is present. The fake-data (demo) path
        // returns plaintext and never decrypts, so an empty key is acceptable there.
        if ('' !== $privateKeyPkcs8) {
            $this->envelope = Envelope::fromPkcs8Base64($privateKeyPkcs8);
        }
    }

    protected function getEnvelope(): Envelope
    {
        if (!$this->envelope instanceof Envelope) {
            throw new ImporterHttpException('open-banking.io private key was not provided; cannot decrypt.');
        }

        return $this->envelope;
    }

    /**
     * Injects a Guzzle handler stack; used only by tests to mock the HTTP transport.
     */
    public function setHandlerStack(HandlerStack $handlerStack): void
    {
        $this->handlerStack = $handlerStack;
    }

    private function getClient(): Client
    {
        $config = [
            'connect_timeout' => $this->timeOut,
            'timeout'         => self::TIMEOUT,
        ];
        if ($this->handlerStack instanceof HandlerStack) {
            $config['handler'] = $this->handlerStack;
        }

        return new Client($config);
    }
}
