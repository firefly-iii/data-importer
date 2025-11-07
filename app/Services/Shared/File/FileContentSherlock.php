<?php

/*
 * FileContentSherlock.php
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
/*
 * FileContentSherlock.php
 * Copyright (c) 2023 james@firefly-iii.org
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

namespace App\Services\Shared\File;

use Exception;
use Genkgo\Camt\Config;
use Genkgo\Camt\Reader;
use Illuminate\Support\Facades\Log;
use Safe\Exceptions\FilesystemException;

use function Safe\file_get_contents;

/**
 * Class FileContentSherlock
 */
class FileContentSherlock
{
    public Reader $camtReader;

    public function __construct()
    {
        $this->camtReader = new Reader(Config::getDefault());
    }

    public function detectContentType(?string $file): string
    {
        if (null === $file) {
            return 'unknown';
        }
        if (!is_readable($file)) {
            return 'unknown';
        }

        try {
            $content = file_get_contents($file);
        } catch (FilesystemException $e) {
            Log::error(sprintf('Cannot read file at %s', $file));
            Log::error($e->getMessage());

            return 'unknown';
        }

        try {
            $this->camtReader->readFile($file);
            Log::debug('CAMT.05x Check on file: positive');

            return $this->detectContentTypeFromContent($content);
        } catch (Exception $e) {
            Log::debug('CAMT.05x Check on file: negative');
            Log::debug($e->getMessage());
        }

        return $this->detectContentTypeFromContent($content);
    }

    public function detectContentTypeFromContent(?string $content): string
    {
        if (null === $content) {
            return 'unknown';
        }

        try {
            $this->camtReader->readString($content);
            Log::debug('CAMT.05x Check of content: positive');

            return 'camt';
        } catch (Exception) {
            Log::debug('CAMT.05x Check of content: negative');
         }

        return 'csv';
    }

    public function getCamtType(): string
    {
        $type = '';
        try {
            // Get Class and Version
            $format = $this->camtReader->getMessageFormat();
            $class = get_class($format);
            Log::debug(sprintf('Class is: %s',$class));
            if (false !== preg_match('/Camt(\d+).*V(\d+)/', $class, $m)) {
                $type = $m[1];      // e. g. 052 or 053
                $version = $m[2];   // e. g. 08
                Log::debug(sprintf('CAMT Type: %s',$type));
                Log::debug(sprintf('CAMT Version: %',$version));
            }
        } catch (Exception) {
            Log::debug('Unable to determine the type and version of CAMT-message');
        }
        return $type;
    }

}
