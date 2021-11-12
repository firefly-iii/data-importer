<?php
declare(strict_types=1);
/**
 * ConfigFileProcessor.php
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

namespace App\Services\CSV\Configuration;


use App\Exceptions\ImportException;
use App\Services\Storage\StorageService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JsonException;
use Log;

/**
 * Class ConfigFileProcessor
 */
class ConfigFileProcessor
{
    /**
     * Input (the content of) a configuration file and this little script will convert it to a compatible array.
     *
     * @param string $fileName
     *
     * @return Configuration
     * @throws ImportException
     */
    public static function convertConfigFile(string $fileName): Configuration
    {
        Log::debug('Now in ConfigFileProcessor::convertConfigFile');
        try {
            $content = StorageService::getContent($fileName);
        } catch (FileNotFoundException $e) {
            Log::error($e->getMessage());
            throw new ImportException(sprintf('Cpuld not find config file: %s', $e->getMessage()));
        }
        try {
            $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::error($e->getMessage());
            throw new ImportException(sprintf('Invalid JSON configuration file: %s', $e->getMessage()));
        }

        return Configuration::fromFile($json);

    }

}
