<?php

/*
 * ConfigFileProcessor.php
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

namespace App\Services\CSV\Configuration;

use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Storage\StorageService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Class ConfigFileProcessor
 */
class ConfigFileProcessor
{
    /**
     * Input (the content of) a configuration file and this little script will convert it to a compatible array.
     *
     * @throws ImporterErrorException
     */
    public static function convertConfigFile(string $fileName): Configuration
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        try {
            $content = StorageService::getContent($fileName);
        } catch (FileNotFoundException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

            throw new ImporterErrorException(sprintf('Could not find config file: %s', $e->getMessage()));
        }

        try {
            $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

            throw new ImporterErrorException(sprintf('Invalid JSON configuration file: %s', $e->getMessage()));
        }

        return Configuration::fromFile($json);
    }
}
