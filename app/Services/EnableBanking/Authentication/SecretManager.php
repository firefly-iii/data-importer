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

namespace App\Services\EnableBanking\Authentication;

use Illuminate\Support\Facades\Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class SecretManager
 */
class SecretManager
{
    public const string EB_APP_ID = 'enable_banking_app_id';
    public const string EB_PRIVATE_KEY = 'enable_banking_private_key';

    /**
     * Get the App ID from session, returns empty string if not found or on exception
     */
    private static function getSessionAppId(): string
    {
        try {
            $id = (string) session()->get(self::EB_APP_ID);
        } catch (ContainerExceptionInterface|NotFoundExceptionInterface) {
            $id = '';
        }

        return $id;
    }

    /**
     * Will return the Enable Banking Application ID. From a cookie if its there, otherwise from configuration.
     */
    public static function getAppId(): string
    {
        $sessionId = self::getSessionAppId();

        if ('' === $sessionId) {
            Log::debug('No Enable Banking App ID in session, will return config variable.');

            return (string) config('enablebanking.application_id');
        }

        return $sessionId;
    }

    /**
     * Will verify if the user has an Enable Banking App ID (in a cookie)
     */
    private static function hasAppId(): bool
    {
        return '' !== self::getSessionAppId();
    }

    /**
     * Get the Private Key from session, returns empty string if not found or on exception
     */
    private static function getSessionPrivateKey(): string
    {
        try {
            $key = (string) session()->get(self::EB_PRIVATE_KEY);
        } catch (ContainerExceptionInterface|NotFoundExceptionInterface) {
            $key = '';
        }

        return $key;
    }

    /**
     * Will return the Enable Banking Private Key. From a cookie if its there, otherwise from configuration.
     */
    public static function getPrivateKey(): string
    {
        $sessionKey = self::getSessionPrivateKey();

        if ('' === $sessionKey) {
            Log::debug('No Enable Banking private key in session, will return config variable.');

            return (string) config('enablebanking.private_key');
        }

        return $sessionKey;
    }

    /**
     * Will verify if the user has an Enable Banking Private Key (in a cookie)
     */
    private static function hasPrivateKey(): bool
    {
        return '' !== self::getSessionPrivateKey();
    }

    /**
     * Check if application ID is available (from session or config)
     */
    public static function hasAppIdAvailable(): bool
    {
        return '' !== self::getAppId();
    }

    /**
     * Check if private key is available (from session or config)
     */
    public static function hasPrivateKeyAvailable(): bool
    {
        return '' !== self::getPrivateKey();
    }

    /**
     * Store application ID in session.
     */
    public static function saveAppId(string $appId): void
    {
        session()->put(self::EB_APP_ID, $appId);
    }

    /**
     * Store private key in session.
     */
    public static function savePrivateKey(string $privateKey): void
    {
        session()->put(self::EB_PRIVATE_KEY, $privateKey);
    }
}
