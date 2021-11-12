<?php
declare(strict_types=1);
/*
 * Token.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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

namespace App\Support;

use App\Exceptions\ImportException;

/**
 * Class Token
 */
class Token
{
    /**
     * @return string
     * @throws ImportException
     */
    public static function getAccessToken(): string
    {
        $value = request()->cookie('access_token');
        if (null === $value) {
            // fall back to config:
            $value = (string) config('csv_importer.access_token');
        }
        if ('' === (string) $value) {
            throw new ImportException('No valid access token value.');
        }
        return (string) $value;
    }

    /**
     * @return string
     * @throws ImportException
     */
    public static function getVanityURL(): string
    {
        $value = request()->cookie('vanity_url');
        if (null === $value) {
            $value = self::getURL();
        }
        if ('' === (string) $value) {
            throw new ImportException('No valid URL value.');
        }
        return (string) $value;
    }

    /**
     * @return string
     * @throws ImportException
     */
    public static function getURL(): string
    {
        $value = request()->cookie('base_url');
        if (null === $value) {
            // fall back to config:
            $value = (string) config('csv_importer.url');
        }
        if ('' === (string) $value) {
            throw new ImportException('No valid URL value.');
        }
        return (string) $value;
    }

}
