<?php
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

        try {
            $message = $this->camtReader->readFile($file);
            app('log')->debug('CAMT.053 Check on file: positive');

            return 'camt';
        } catch (Exception $e) {
            app('log')->debug('CAMT.053 Check on file: negative');
            //app('log')->debug($e->getMessage());
        }

        return 'csv';
    }

    public function detectContentTypeFromContent(?string $content): string
    {
        if (null === $content) {
            return 'unknown';
        }
        try {
            $this->camtReader->readString($content);
            app('log')->debug('CAMT.053 Check of content: positive');

            return 'camt';
        } catch (Exception $e) {
            app('log')->debug('CAMT.053 Check of content: negative');
            //app('log')->debug($e->getMessage());
        }

        return 'csv';
    }
}
