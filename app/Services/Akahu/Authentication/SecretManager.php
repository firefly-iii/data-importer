<?php

/*
 * SecretManager.php
 * Copyright (c) 2026 james@firefly-iii.org
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

namespace App\Services\Akahu\Authentication;

use SensitiveParameter;

final class SecretManager
{
    public const string APP_ID_TOKEN      = 'app_id_token';
    public const string USER_ACCESS_TOKEN = 'user_access_token';

    public static function getAppIdToken(): string
    {
        if (session()->exists(self::APP_ID_TOKEN)) {
            return session()->get(self::APP_ID_TOKEN);
        }

        return config(sprintf('akahu.%s', self::APP_ID_TOKEN));
    }

    public static function getUserAccessToken(): string
    {
        if (session()->exists(self::USER_ACCESS_TOKEN)) {
            return session()->get(self::USER_ACCESS_TOKEN);
        }

        return config(sprintf('akahu.%s', self::USER_ACCESS_TOKEN));
    }

    public static function setAppIdToken(#[SensitiveParameter] string $token): void
    {
        session()->put(self::APP_ID_TOKEN, $token);
    }

    public static function setUserAccessToken(#[SensitiveParameter] string $token): void
    {
        session()->put(self::USER_ACCESS_TOKEN, $token);
    }

    public static function hasAppIdToken(): bool
    {
        return '' !== self::getAppIdToken();
    }

    public static function hasUserAccessToken(): bool
    {
        return '' !== self::getUserAccessToken();
    }

    public static function getSecrets(): array
    {
        return [self::APP_ID_TOKEN => self::getAppIdToken(), self::USER_ACCESS_TOKEN => self::getUserAccessToken()];
    }

    public static function setSecrets(array $data): void
    {
        self::setAppIdToken($data[self::APP_ID_TOKEN]);
        self::setUserAccessToken($data[self::USER_ACCESS_TOKEN]);
    }
}
