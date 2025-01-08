<?php
/*
 * RequestCache.php
 * Copyright (c) 2024 james@firefly-iii.org
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

use Illuminate\Support\Facades\Log;

class RequestCache
{
    public static function has(string $identifier, string $token): bool
    {
        Log::debug('has key in cache?');
        $key = self::generateKey($identifier, $token);
        $result = cache()->has($key);
        Log::debug(sprintf('has key "%s" in cache? %s', substr($key,0,10), $result ? 'yes' : 'no'));
        return $result;
    }

    public static function get(string $identifier, string $token): mixed
    {
        Log::debug('get!');
        $key = self::generateKey($identifier, $token);
        return cache()->get($key);
    }

    public static function set(string $identifier, string $token, mixed $data): void
    {
        Log::debug('set key forever!');
        $key = self::generateKey($identifier, $token);
        cache()->forever($key, $data);
    }

    private static function generateKey(string $identifier, string $token): string {
        $hash =  hash('sha256', sprintf('%s-%s', $identifier, $token));
        Log::debug(sprintf('generateKey("%s", "%s...") results in "%s..."', $identifier, substr($token,0,10), substr($hash,0,10)));
        return $hash;
    }

}
