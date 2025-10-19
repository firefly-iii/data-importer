<?php

/*
 * SecretManager.php
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

namespace App\Services\Spectre\Authentication;

use Illuminate\Support\Facades\Log;

/**
 * Class SecretManager
 */
class SecretManager
{
    public const string APP_ID = 'spectre_app_id';
    public const string SECRET = 'spectre_secret';

    /**
     * Will return the Nordigen ID. From a cookie if its there, otherwise from configuration.
     */
    public static function getAppId(): string
    {
        if (!self::hasAppId()) {
            Log::debug('No Spectre App ID in hasAppId() session, will return config variable.');

            return (string) config('spectre.app_id');
        }

        return (string) session()->get(self::APP_ID);
    }

    /**
     * Will verify if the user has a Spectre App ID (in a cookie)
     * TODO is a cookie the best place?
     */
    private static function hasAppId(): bool
    {
        return '' !== (string) session()->get(self::APP_ID);
    }

    /**
     * Will return the Nordigen ID. From a cookie if its there, otherwise from configuration.
     * TODO is a cookie the best place?
     */
    public static function getSecret(): string
    {
        if (!self::hasSecret()) {
            Log::debug('No Spectre secret in hasSecret(), will return config variable.');

            return (string) config('spectre.secret');
        }

        return (string) session()->get(self::SECRET);
    }

    /**
     * Will verify if the user has a Spectre App ID (in a cookie)
     */
    private static function hasSecret(): bool
    {
        return '' !== (string) session()->get(self::SECRET);
    }

    /**
     * Store app ID.
     * TODO is a cookie the best place?
     */
    public static function saveAppId(string $appId): void
    {
        session()->put(self::APP_ID, $appId);
    }

    /**
     * Store access token in a cookie.
     * TODO is a cookie the best place?
     */
    public static function saveSecret(string $secret): void
    {
        session()->put(self::SECRET, $secret);
    }
}
