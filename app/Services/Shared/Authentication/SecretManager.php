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

use Symfony\Component\HttpFoundation\Cookie;

/**
 * Class SecretManager
 */
class SecretManager
{
    public const ACCESS_TOKEN  = 'access_token';
    public const BASE_URL      = 'base_url';
    public const REFRESH_TOKEN = 'refresh_token';
    public const VANITY_URL    = 'vanity_url';

    /**
     * Will return the access token. From a cookie if its there, otherwise from configuration.
     *
     * @return string
     */
    public static function getAccessToken(): string
    {
        if (!self::hasAccessToken()) {
            app('log')->debug('No access token in hasAccessToken(), will return config variable.');

            return (string)config('importer.access_token');
        }

        return request()->cookie(self::ACCESS_TOKEN);
    }

    /**
     * @return string
     */
    public static function getBaseUrl(): string
    {
        if (!self::hasBaseUrl()) {
            app('log')->debug('No base url in getBaseUrl(), will return config variable.');

            return (string)config('importer.url');
        }

        return (string)request()->cookie(self::BASE_URL);
    }

    /**
     * Will return the client ID. From a cookie if its there, otherwise from configuration.
     *
     * @return int
     */
    public static function getClientId(): int
    {
        if (!self::hasClientId()) {
            app('log')->debug('No client id in hasClientId(), will return config variable.');

            return (int)config('importer.client_id');
        }

        return (int)request()->cookie('client_id');
    }

    /**
     * @return string
     */
    public static function getVanityUrl(): string
    {
        if (!self::hasVanityUrl()) {
            app('log')->debug('No vanity url in getVanityUrl(), will return config variable.');
            if ('' === (string)config('importer.vanity_url')) {
                return (string)config('importer.url');
            }

            return (string)config('importer.vanity_url');
        }

        return (string)request()->cookie(self::VANITY_URL);
    }

    /**
     * Will return true if the session / cookies hold valid secrets (access token, URLs)
     *
     * @return bool
     */
    public static function hasValidSecrets(): bool
    {
        app('log')->debug(__METHOD__);
        // check for access token cookie. if not, redirect to flow to get it.
        if (!self::hasAccessToken() && !self::hasRefreshToken() && !self::hasBaseUrl()) {
            return false;
        }

        return true;
        //        $accessToken  = (string) $request->cookie('access_token');
        //        $refreshToken = (string) $request->cookie('refresh_token');
        //        $baseURL      = (string) $request->cookie('base_url');
        //        $vanityURL    = (string) $request->cookie('vanity_url');
        //
        //        app('log')->debug(sprintf('Base URL   : "%s"', $baseURL));
        //        app('log')->debug(sprintf('Vanity URL : "%s"', $vanityURL));
        //
        //        if ('' === $accessToken && '' === $refreshToken && '' === $baseURL) {
        //            app('log')->debug('No access token cookie, redirect to token.index');
        //            return redirect(route('token.index'));
        //        }
    }

    /**
     * Store access token in a cookie.
     * TODO is a cookie the best place?
     *
     * @param  string  $token
     *
     * @return Cookie
     */
    public static function saveAccessToken(string $token): Cookie
    {
        return cookie(self::ACCESS_TOKEN, $token);
    }

    /**
     * Store access token in a cookie.
     * TODO is a cookie the best place?
     *
     * @param  string  $url
     *
     * @return Cookie
     */
    public static function saveBaseUrl(string $url): Cookie
    {
        return cookie(self::BASE_URL, $url);
    }

    /**
     * Store access token in a cookie.
     * TODO is a cookie the best place?
     *
     * @param  string  $token
     *
     * @return Cookie
     */
    public static function saveRefreshToken(string $token): Cookie
    {
        return cookie(self::REFRESH_TOKEN, $token);
    }

    /**
     * Store access token in a cookie.
     * TODO is a cookie the best place?
     *
     * @param  string  $url
     *
     * @return Cookie
     */
    public static function saveVanityUrl(string $url): Cookie
    {
        return cookie(self::VANITY_URL, $url);
    }

    /**
     * Will verify if the user has an access token (in a cookie)
     * TODO is a cookie the best place?
     *
     * @return bool
     */
    private static function hasAccessToken(): bool
    {
        return '' !== (string)request()->cookie(self::ACCESS_TOKEN);
    }

    /**
     * Will verify if the user has an base URL defined (in a cookie)
     * TODO is a cookie the best place?
     *
     * @return bool
     */
    private static function hasBaseUrl(): bool
    {
        return '' !== (string)request()->cookie(self::BASE_URL);
    }

    /**
     * Will verify if the user has an client ID defined (in a cookie)
     * TODO is a cookie the best place?
     *
     * @return bool
     */
    private static function hasClientId(): bool
    {
        return '' !== (string)request()->cookie('client_id');
    }

    /**
     * Will verify if the user has an refresh token (in a cookie)
     * TODO is a cookie the best place?
     *
     * @see self::hasAccessToken
     */
    private static function hasRefreshToken(): bool
    {
        return '' !== (string)request()->cookie(self::REFRESH_TOKEN);
    }

    /**
     * Will verify if the user has a vanity URL defined (in a cookie)
     * TODO is a cookie the best place?
     *
     * @return bool
     */
    private static function hasVanityUrl(): bool
    {
        return '' !== (string)request()->cookie(self::VANITY_URL);
    }
}
