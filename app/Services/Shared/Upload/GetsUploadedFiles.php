<?php
/*
 * GetsUploadedFiles.php
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

use Illuminate\Http\Request;

/**
 * Trait GetsUploadedFiles
 */
trait GetsUploadedFiles
{
    /**
     * @param Request $request
     * @return array
     */
    protected function getFilesFromUpload(Request $request): array
    {
        $importableFiles = $request->file('importable_file');
        $configFiles     = $request->file('config_file');

        // merge these into a single array if both are arrays
        if (is_array($importableFiles) && is_array($configFiles)) {
            return array_merge($importableFiles, $configFiles);
        }

        // create array if both are a file.
        if (!is_array($importableFiles) && !is_array($configFiles)) {
            return [$importableFiles, $configFiles];
        }

        // create array if one is a file:
        if (!is_array($importableFiles) && is_array($configFiles)) {
            $configFiles[] = $importableFiles;
            return $configFiles;
        }

        // create array if other is a file:
        if (is_array($importableFiles) && !is_array($configFiles) && null !== $configFiles) {
            $importableFiles[] = $configFiles;
            return $importableFiles;
        }
        // create array if other is a file:
        if (is_array($importableFiles) && !is_array($configFiles) && null === $configFiles) {
            return $importableFiles;
        }

        return [];
    }

}
