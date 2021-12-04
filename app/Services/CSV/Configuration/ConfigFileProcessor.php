<?php
/*
 * ConfigFileProcessor.php
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

namespace App\Services\CSV\Configuration;


use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Configuration\Configuration;
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
     * @throws ImporterErrorException
     */
    public static function convertConfigFile(string $fileName): Configuration
    {
        Log::debug('Now in ConfigFileProcessor::convertConfigFile');
        try {
            $content = StorageService::getContent($fileName);
        } catch (FileNotFoundException $e) {
            Log::error($e->getMessage());
            throw new ImporterErrorException(sprintf('Cpuld not find config file: %s', $e->getMessage()));
        }
        try {
            $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::error($e->getMessage());
            throw new ImporterErrorException(sprintf('Invalid JSON configuration file: %s', $e->getMessage()));
        }

        return Configuration::fromFile($json);

    }

}
