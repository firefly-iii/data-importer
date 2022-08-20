<?php
/*
 * GetsConfigFromCombination.php
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

namespace App\Services\Shared\Configuration;

use App\Exceptions\ImporterErrorException;
use App\Services\Storage\StorageService;
use UnexpectedValueException;

/**
 * Trait GetsConfigFromCombination
 */
trait GetsConfigFromCombination
{
    /**
     * Parse config file, and return object. Throws error.
     * @param array $data
     * @return Configuration
     * @throws ImporterErrorException
     */
    protected function getConfigFromCombination(array $data): Configuration
    {
        try {
            $content = StorageService::getContent($data['config_location'] ?? 'invalid');
        } catch (UnexpectedValueException $e) {
            throw new ImporterErrorException('The configuration could not be found.');
        }
        return Configuration::fromArray(json_decode($content, true));
    }

}
