<?php
declare(strict_types=1);
/**
 * StorageService.php
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

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JsonException;
use Log;
use Storage;
use Str;
use UnexpectedValueException;

/**
 * Class StorageService
 */
class StorageService
{
    /**
     * @param string $content
     *
     * @return string
     */
    public static function storeContent(string $content): string
    {
        $fileName = hash('sha256', $content);
        $disk     = Storage::disk('uploads');

        if($disk->has($fileName)) {
            Log::warning(sprintf('Have already stored a file under key "%s", so the content is unchanged from last time.', $fileName));
        }

        $disk->put($fileName, $content);
        Log::debug(sprintf('storeContent: Stored %d bytes in file "%s"', strlen($content), $fileName));

        return $fileName;
    }

    /**
     * @param array $array
     * @return string
     * @throws JsonException
     */
    public static function storeArray(array $array): string
    {
        $disk     = Storage::disk('uploads');
        $json     = json_encode($array, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT, 256);
        $fileName = hash('sha256', $json);

        if($disk->has($fileName)) {
            Log::warning(sprintf('Have already stored a file under key "%s", so the content is unchanged from last time.', $fileName));
        }

        $disk->put($fileName, $json);
        Log::debug(sprintf('storeArray: Stored %d bytes in file "%s"', strlen($json), $fileName));

        return $fileName;
    }

    /**
     * @param string $name
     *
     * @return string
     * @throws FileNotFoundException
     */
    public static function getContent(string $name): string
    {
        $disk = Storage::disk('uploads');
        if ($disk->exists($name)) {
            return $disk->get($name);
        }
        throw new UnexpectedValueException(sprintf('No such file %s', $name));
    }

}
