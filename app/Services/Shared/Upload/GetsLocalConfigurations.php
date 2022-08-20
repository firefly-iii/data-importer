<?php
/*
 * GetsLocalConfigurations.php
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

use Illuminate\Support\Facades\Storage;

/**
 * Trait GetsLocalConfigurations
 */
trait GetsLocalConfigurations
{
    /**
     * @return array
     */
    protected function getLocalConfigs(): array
    {
        $list = [];
        // get existing configurations on disk.
        $disk = Storage::disk('configurations');
        app('log')->debug(
            sprintf(
                'Going to check directory for existing config files: %s',
                config('filesystems.disks.configurations.root'),
            )
        );
        $all = $disk->files();

        // remove files from list
        $ignored = config('importer.ignored_files');
        foreach ($all as $entry) {
            if (!in_array($entry, $ignored, true)) {
                $list[] = $entry;
            }
        }

        app('log')->debug('List of files:', $list);
        return $list;
    }
}
