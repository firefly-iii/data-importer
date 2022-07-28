<?php
/*
 * DetectsFileType.php
 * Copyright (c) 2022 james@firefly-iii.org
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

namespace App\Services\Shared\Upload;

/**
 * Trait DetectsFileType
 */
trait DetectsFileType
{
    /**
     * This method detects the file type of the uploaded file.
     *
     * @param string $path
     * @return string
     */
    protected function detectFileType(string $path): string
    {
        app('log')->debug(sprintf('Now in %s("%s")', __METHOD__, $path));
        $fileType   = mime_content_type($path);
        $returnType = 'unknown';
        switch ($fileType) {
            case 'application/csv':
            case 'text/csv':
            case 'text/plain':
                // here we can always dive into the exact file content to make sure it's CSV.
                $returnType = 'text';
                break;
            case 'application/json':
                $returnType = 'json';
                break;
            case 'application/zip':
                $returnType = 'zip';
                break;
            case 'text/xml':
                // here we can always dive into the exact file content.
                $returnType = 'xml';
                break;
        }
        app('log')->debug(sprintf('Mime seems to be "%s", so return "%s".', $fileType, $returnType));

        return $returnType;
    }
}
