<?php
/*
 * FileReader.php
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

namespace App\Services\CSV\File;


use App\Services\Session\Constants;
use App\Services\Storage\StorageService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use League\Csv\Reader;
use Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class FileReader
 */
class FileReader
{
    /**
     * Get a CSV file reader and fill it with data from CSV file.
     *
     * @param bool $convert
     * @return Reader
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function getReaderFromSession(bool $convert = false): Reader
    {
        $content = StorageService::getContent(session()->get(Constants::UPLOAD_CSV_FILE), $convert);

        // room for config
        return Reader::createFromString($content);
    }

    /**
     * @param string $content
     * @param bool   $convert
     * @return Reader
     */
    public static function getReaderFromContent(string $content, bool $convert = false): Reader
    {
        if (true === $convert) {
            $encoding = mb_detect_encoding($content, config('importer.encoding'), true);
            if (false !== $encoding && 'ASCII' !== $encoding) {
                Log::warning(sprintf('Content is detected as "%s" and will be converted to UTF-8. Your milage may vary.', $encoding));
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            }
        }
        return Reader::createFromString($content);
    }

}
