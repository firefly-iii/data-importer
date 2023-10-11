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

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
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
     *
     * @return string
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function getId(): string
    {
        if (!self::hasId()) {
            app('log')->debug('No Nordigen ID in hasId() session, will return config variable.');

            return (string)config('nordigen.id');
        }
        return (string)session()->get(self::NORDIGEN_ID);
    }

    /**
     * Will return the Nordigen ID. From a cookie if its there, otherwise from configuration.
     *
     * @return string
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function getKey(): string
    {
        if (!self::hasKey()) {
            app('log')->debug('No Nordigen key in hasKey() session, will return config variable.');

            return (string)config('nordigen.key');
        }

        return (string)session()->get(self::NORDIGEN_KEY);
    }

    /**
     * Store access token in a cookie.
     *
     * @param string $identifier
     *
     * @return void
     */
    public static function saveId(string $identifier): void
    {
        session()->put(self::NORDIGEN_ID, $identifier);
    }

    /**
     * Store access token in a cookie.
     *
     * @param string $key
     *
     * @return void
     */
    public static function saveKey(string $key): void
    {
        session()->put(self::NORDIGEN_KEY, $key);
    }

    /**
     * Will verify if the user has a Nordigen ID (in a cookie)
     *
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private static function hasId(): bool
    {
        return '' !== (string)session()->get(self::NORDIGEN_ID);
    }

    /**
     * Will verify if the user has a Nordigen Key (in a cookie)
     *
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private static function hasKey(): bool
    {
        return '' !== (string)session()->get(self::NORDIGEN_KEY);
    }
}
