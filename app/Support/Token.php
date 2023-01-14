<?php
/*
 * Token.php
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

namespace App\Support;

use App\Exceptions\ImporterErrorException;

/**
 * Class Token
 */
class Token
{
    /**
     * @return string
     * @throws ImporterErrorException
     */
    public static function getAccessToken(): string
    {
        $value = request()->cookie('access_token');
        if (null === $value) {
            // fall back to config:
            $value = (string) config('importer.access_token');
        }
        if ('' === (string) $value) {
            throw new ImporterErrorException('No valid access token value.');
        }
        return (string) $value;
    }

    /**
     * @return string
     * @throws ImporterErrorException
     */
    public static function getVanityURL(): string
    {
        $value = request()->cookie('vanity_url');
        if (null === $value) {
            $value = config('importer.vanity_url');
        }
        if (null === $value) {
            $value = self::getURL();
        }
        if ('' === (string) $value) {
            throw new ImporterErrorException('No valid URL value.');
        }
        return (string) $value;
    }

    /**
     * @return string
     * @throws ImporterErrorException
     */
    public static function getURL(): string
    {
        $value = request()->cookie('base_url');
        if (null === $value) {
            // fall back to config:
            $value = (string) config('importer.url');
        }
        if ('' === (string) $value) {
            throw new ImporterErrorException('No valid URL value.');
        }
        return (string) $value;
    }
}
