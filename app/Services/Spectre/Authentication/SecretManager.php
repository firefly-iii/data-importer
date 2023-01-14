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

namespace App\Services\Spectre\Authentication;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * Class SecretManager
 */
class SecretManager
{
    public const APP_ID = 'spectre_app_id';
    public const SECRET = 'spectre_secret';


    /**
     * Will return the Nordigen ID. From a cookie if its there, otherwise from configuration.
     * TODO is a cookie the best place?
     *
     * @return string
     */
    public static function getAppId(): string
    {
        if (!self::hasAppId()) {
            app('log')->debug('No Spectre App ID in hasAppId(), will return config variable.');
            return (string) config('spectre.app_id');
        }
        return request()->cookie(self::APP_ID);
    }

    /**
     * Will verify if the user has a Spectre App ID (in a cookie)
     * TODO is a cookie the best place?
     *
     * @return bool
     */
    private static function hasAppId(): bool
    {
        return '' !== (string) request()->cookie(self::APP_ID);
    }

    /**
     * Will return the Nordigen ID. From a cookie if its there, otherwise from configuration.
     * TODO is a cookie the best place?
     *
     * @return string
     */
    public static function getSecret(): string
    {
        if (!self::hasSecret()) {
            app('log')->debug('No Spectre secret in hasSecret(), will return config variable.');
            return (string) config('spectre.secret');
        }
        return request()->cookie(self::SECRET);
    }

    /**
     * Will verify if the user has a Spectre App ID (in a cookie)
     * TODO is a cookie the best place?
     *
     * @return bool
     */
    private static function hasSecret(): bool
    {
        return '' !== (string) request()->cookie(self::SECRET);
    }


    /**
     * Store app ID.
     * TODO is a cookie the best place?
     *
     * @param string $appId
     * @return Cookie
     */
    public static function saveAppId(string $appId): Cookie
    {
        return cookie(self::APP_ID, $appId);
    }

    /**
     * Store access token in a cookie.
     * TODO is a cookie the best place?
     *
     * @param string $secret
     * @return Cookie
     */
    public static function saveSecret(string $secret): Cookie
    {
        return cookie(self::SECRET, $secret);
    }
}
