<?php

/*
 * SimpleFINResponse.php
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

namespace App\Services\SimpleFIN\Response;

use App\Services\Shared\Response\ResponseInterface as SharedResponseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class SimpleFINResponse
 */
abstract class SimpleFINResponse implements SharedResponseInterface
{
    private array $data = [];
    private int $statusCode;
    private string $rawBody;

    public function __construct(ResponseInterface $response)
    {
        $this->statusCode = $response->getStatusCode();
        $this->rawBody    = (string) $response->getBody();

        app('log')->debug(sprintf('SimpleFIN Response: HTTP %d', $this->statusCode));
        app('log')->debug(sprintf('SimpleFIN Response body: %s', $this->rawBody));

        $this->parseResponse();
    }

    /**
     * Check if the response has an error
     */
    public function hasError(): bool
    {
        return $this->statusCode >= 400;
    }

    /**
     * Get the HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function getData(): array
    {
        return $this->data;
    }

    protected function setData(array $data): void
    {
        $this->data = $data;
    }

    private function parseResponse(): void
    {
        if (empty($this->rawBody)) {
            app('log')->warning('SimpleFIN Response body is empty');
            $this->data = [];

            return;
        }

        $decoded    = json_decode($this->rawBody, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            app('log')->error(sprintf('SimpleFIN JSON decode error: %s', json_last_error_msg()));
            $this->data = [];

            return;
        }

        if (!is_array($decoded)) {
            app('log')->error('SimpleFIN Response is not a valid JSON array');
            $this->data = [];

            return;
        }

        $this->data = $decoded;
        app('log')->debug('SimpleFIN Response parsed successfully');
    }
}
