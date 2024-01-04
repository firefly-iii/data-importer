<?php
/*
 * StorageService.php
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

namespace App\Services\Storage;

use App\Exceptions\ImporterErrorException;
use League\Flysystem\FilesystemException;

/**
 * Class StorageService
 */
class StorageService
{
    public static function getContent(string $name, bool $convert = false): string
    {
        $disk     = \Storage::disk('uploads');
        if (!$disk->exists($name)) {
            throw new \UnexpectedValueException(sprintf('No such file %s', $name));
        }
        if (false === $convert) {
            return $disk->get($name);
        }
        $content  = $disk->get($name);
        $encoding = mb_detect_encoding($content, config('importer.encoding'), true);
        if (false === $encoding) {
            app('log')->warning('Tried to detect encoding but could not find valid encoding. Assume UTF-8.');

            return $content;
        }
        if ('ASCII' === $encoding || 'UTF-8' === $encoding) {
            return $content;
        }
        app('log')->warning(sprintf('Content is detected as "%s" and will be converted to UTF-8. Your milage may vary.', $encoding));

        return mb_convert_encoding($content, 'UTF-8', $encoding);
    }

    /**
     * @throws FilesystemException
     * @throws \JsonException
     */
    public static function storeArray(array $array): string
    {
        $disk     = \Storage::disk('uploads');
        $json     = json_encode($array, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT, 256);
        $fileName = hash('sha256', $json);

        if ($disk->has($fileName)) {
            app('log')->warning(sprintf('Have already stored a file under key "%s", so the content is unchanged from last time.', $fileName));
        }

        $disk->put($fileName, $json);
        app('log')->debug(sprintf('storeArray: Stored %d bytes in file "%s"', strlen($json), $fileName));

        return $fileName;
    }

    /**
     * @throws FilesystemException
     * @throws ImporterErrorException
     */
    public static function storeContent(string $content): string
    {
        $fileName = hash('sha256', $content);
        $disk     = \Storage::disk('uploads');
        if ('{}' === $content) {
            throw new ImporterErrorException('Content is {}');
        }

        if ($disk->has($fileName)) {
            app('log')->warning(sprintf('Have already stored a file under key "%s", so the content is unchanged from last time.', $fileName));
        }

        $disk->put($fileName, $content);
        app('log')->debug(sprintf('storeContent: Stored %d bytes in file "%s"', strlen($content), $fileName));

        return $fileName;
    }
}
