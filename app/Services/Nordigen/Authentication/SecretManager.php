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

namespace App\Services\Nordigen\Authentication;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * Class SecretManager
 */
class SecretManager
{
    public const NORDIGEN_ID  = 'nordigen_id';
    public const NORDIGEN_KEY = 'nordigen_key';

    /**
     * Will return the Nordigen ID. From a cookie if its there, otherwise from configuration.
     * TODO is a cookie the best place?
     *
     * @return string
     */
    public static function getId(): string
    {
        if (!self::hasId()) {
            app('log')->debug('No Nordigen ID in hasId(), will return config variable.');

            return (string)config('nordigen.id');
        }

        return request()->cookie(self::NORDIGEN_ID);
    }

    /**
     * Will verify if the user has a Nordigen ID (in a cookie)
     * TODO is a cookie the best place?
     *
     * @return bool
     */
    private static function hasId(): bool
    {
        return '' !== (string)request()->cookie(self::NORDIGEN_ID);
    }

    /**
     * Will return the Nordigen ID. From a cookie if its there, otherwise from configuration.
     *
     * @return string
     */
    public static function getKey(): string
    {
        if (!self::hasKey()) {
            app('log')->debug('No Nordigen key in hasKey(), will return config variable.');

            return (string)config('nordigen.key');
        }

        return request()->cookie(self::NORDIGEN_KEY);
    }

    /**
     * Will verify if the user has a Nordigen Key (in a cookie)
     * TODO is a cookie the best place?
     *
     * @return bool
     */
    private static function hasKey(): bool
    {
        return '' !== (string)request()->cookie(self::NORDIGEN_KEY);
    }

    /**
     * Store access token in a cookie.
     * TODO is a cookie the best place?
     *
     * @param string $identifier
     *
     * @return Cookie
     */
    public static function saveId(string $identifier): Cookie
    {
        return cookie(self::NORDIGEN_ID, $identifier);
    }

    /**
     * Store access token in a cookie.
     * TODO is a cookie the best place?
     *
     * @param string $key
     *
     * @return Cookie
     */
    public static function saveKey(string $key): Cookie
    {
        return cookie(self::NORDIGEN_KEY, $key);
    }
}
