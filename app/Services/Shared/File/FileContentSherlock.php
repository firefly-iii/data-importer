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

use Genkgo\Camt\Config;
use Genkgo\Camt\Reader;
use Illuminate\Support\Facades\Log;
use Exception;

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

    public function detectContentType(?string $file, &$camtType = null): string
    {
        if (null === $file) {
            return 'unknown';
        }

        try {
            $this->camtReader->readFile($file);
            Log::debug('CAMT Check on file: positive');
            $camtType = $this->extractCamtType($this->camtReader);

            return 'camt';
        } catch (Exception $e) {
            Log::debug('CAMT Check on file: negative');
            Log::debug($e->getMessage());
        }

        return 'csv';
    }

    public function detectContentTypeFromContent(?string $content, &$camtType = null): string
    {
        if (null === $content) {
            return 'unknown';
        }

        try {
            $this->camtReader->readString($content);
            Log::debug('CAMT Check of content: positive');
            $camtType = $this->extractCamtType($this->camtReader);

            return 'camt';
        } catch (Exception) {
            Log::debug('CAMT Check of content: negative');
            // Log::debug($e->getMessage());
        }

        return 'csv';
    }

    private function extractCamtType(Reader $reader)
    {
        if(null === $reader) {
            return null;
        }
        
        try {
            // Get Class and Version
            $format = $reader->getMessageFormat();
            $class = get_class($format);
            if (preg_match('/Camt(\d+).*V(\d+)/', $class, $m)) {
                $type = $m[1];      // e. g. 052 or 053
                $version = $m[2];   // e. g. 08
                Log::debug('CAMT Type: '.$type);
                Log::debug('CAMT Version: '.$version);
            }
            return $type;
        } catch (Exception) {
            Log::debug('Unable to determine the type and version of CAMT-message');
        }
        
    }
}
