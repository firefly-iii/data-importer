<?php

/*
 * SecretManager.php
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

namespace App\Services\Shared\Authentication;

use App\Services\Session\Constants;
use Illuminate\Support\Facades\Log;

/**
 * Class SecretManager
 */
class SecretManager
{
    /**
     * Will return the access token. From a cookie or header if it's there, otherwise from configuration.
     */
    public static function getAccessToken(): string
    {
        if (session()->has(Constants::SESSION_ACCESS_TOKEN)) {
            $token = session()->get(Constants::SESSION_ACCESS_TOKEN);
            if ('' !== $token) {
                return $token;
            }
        }

        Log::debug('No access token in session, will return header or config variable.');

        $token = request()?->header('Authorization', '') ?? '';
        if (is_array($token)) {
            $token = (string) reset($token);
        }
        if('' === $token) {
            Log::debug('Access token in header is empty, will be ignored.');
            $token = null;
        }
        if (is_string($token) && strlen($token) > 0 && !str_contains($token, 'Bearer ')) {
            Log::debug('Access token in header is not a Bearer token, will be ignored.');
            $token = null;
        }
        if (is_string($token) && strlen($token) > 0 && str_contains($token, 'Bearer ')) {
            Log::debug('Access token in header is a Bearer token, will be used.');
            $token = str_replace('Bearer ', '', $token);
        }
        if (null === $token) {
            Log::debug('Access token is null, use config instead.');
            $token = (string)config('importer.access_token');
        }
        return (string)$token;
    }

    public static function getBaseUrl(): string
    {
        if (!self::hasBaseUrl()) {
            Log::debug('No base url in getBaseUrl() session, will return config variable.');

            return (string)config('importer.url');
        }

        return (string)session()->get(Constants::SESSION_BASE_URL);
    }

    /**
     * Will verify if the user has an base URL defined (in a cookie)
     */
    private static function hasBaseUrl(): bool
    {
        return session()->has(Constants::SESSION_BASE_URL) && '' !== session()->get(Constants::SESSION_BASE_URL);
    }

    /**
     * Will return the client ID. From a cookie if its there, otherwise from configuration.
     */
    public static function getClientId(): int
    {
        if (!self::hasClientId()) {
            Log::debug('No client id in hasClientId() session, will return config variable.');

            return (int)config('importer.client_id');
        }

        return (int)session()->get(Constants::SESSION_CLIENT_ID);
    }

    /**
     * Will verify if the user has an client ID defined
     */
    private static function hasClientId(): bool
    {
        return session()->has(Constants::SESSION_CLIENT_ID) && 0 !== session()->get(Constants::SESSION_CLIENT_ID);
    }

    public static function getVanityUrl(): string
    {
        if (!self::hasVanityUrl()) {
            Log::debug('No vanity url in getVanityUrl() session, will return config variable.');
            if ('' === (string)config('importer.vanity_url')) {
                return (string)config('importer.url');
            }

            return (string)config('importer.vanity_url');
        }

        return (string)session()->get(Constants::SESSION_VANITY_URL);
    }

    /**
     * Will verify if the user has a vanity URL defined
     */
    private static function hasVanityUrl(): bool
    {
        return session()->has(Constants::SESSION_VANITY_URL) && '' !== session()->get(Constants::SESSION_VANITY_URL);
    }

    /**
     * Will return true if the session / cookies hold valid secrets (access token, URLs)
     */
    public static function hasValidSecrets(): bool
    {
        Log::debug(__METHOD__);
        // check for access token cookie. if not, redirect to flow to get it.
        if ('' === self::getAccessToken() && !self::hasRefreshToken() && !self::hasBaseUrl()) {
            return false;
        }

        return true;
    }

    /**
     * Will verify if the user has a refresh token
     *
     * @see self::hasAccessToken
     */
    private static function hasRefreshToken(): bool
    {
        return session()->has(Constants::SESSION_REFRESH_TOKEN) && '' !== session()->get(Constants::SESSION_REFRESH_TOKEN);
    }

    /**
     * Store access token.
     */
    public static function saveAccessToken(string $token): void
    {
        session()->put(Constants::SESSION_ACCESS_TOKEN, $token);
    }

    /**
     * Store access token.
     */
    public static function saveBaseUrl(string $url): void
    {
        session()->put(Constants::SESSION_BASE_URL, $url);
    }

    /**
     * Store access token.
     */
    public static function saveRefreshToken(string $token): void
    {
        session()->put(Constants::SESSION_REFRESH_TOKEN, $token);
    }

    /**
     * Store access token.
     */
    public static function saveVanityUrl(string $url): void
    {
        session()->put(Constants::SESSION_VANITY_URL, $url);
    }
}
