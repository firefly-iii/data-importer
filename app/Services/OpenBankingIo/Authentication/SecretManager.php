<?php

/*
 * SecretManager.php
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

namespace App\Services\OpenBankingIo\Authentication;

use App\Services\Shared\Configuration\Configuration;
use SensitiveParameter;

/**
 * Class SecretManager
 *
 * Stores and reads the open-banking.io "credentials.json" bundle (API key + private key).
 * The raw bundle lives in the session (or the import configuration) as a single JSON string.
 */
final class SecretManager
{
    public const string CREDENTIALS = 'obio_credentials';

    /**
     * Returns the raw credentials bundle JSON (session -> env config -> import configuration).
     */
    public static function getCredentials(?Configuration $configuration = null): string
    {
        if (self::hasCredentials()) {
            return (string) session()->get(self::CREDENTIALS);
        }

        $fromConfig = (string) config('obio.credentials');
        if ('' !== $fromConfig) {
            return $fromConfig;
        }

        return (string) $configuration?->getObioCredentials();
    }

    public static function saveCredentials(#[SensitiveParameter] string $credentials): void
    {
        session()->put(self::CREDENTIALS, $credentials);
    }

    private static function hasCredentials(): bool
    {
        return '' !== (string) session()->get(self::CREDENTIALS);
    }

    /**
     * Parses the credentials bundle into its parts.
     *
     * @return array{apiBaseUrl: string, apiKey: string, privateKey: string}
     */
    public static function parse(#[SensitiveParameter] string $credentials): array
    {
        $bundle     = json_decode($credentials, true);
        $bundle     = is_array($bundle) ? $bundle : [];
        $encryption = is_array($bundle['encryptionKey'] ?? null) ? $bundle['encryptionKey'] : [];

        $apiBaseUrl = (string) ($bundle['apiBaseUrl'] ?? config('obio.api_url'));
        if ('' === $apiBaseUrl) {
            $apiBaseUrl = (string) config('obio.api_url');
        }

        return [
            'apiBaseUrl' => rtrim($apiBaseUrl, '/'),
            'apiKey'     => (string) ($bundle['apiKey'] ?? ''),
            'privateKey' => (string) ($encryption['privateKey'] ?? $encryption['privateKeyPkcs8B64'] ?? ''),
        ];
    }
}
