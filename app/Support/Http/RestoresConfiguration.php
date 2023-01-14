<?php

/*
 * RestoresConfiguration.php
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

namespace App\Support\Http;

use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Storage\StorageService;

trait RestoresConfiguration
{
    /**
     * Restore configuration from session and drive.
     *
     * @return Configuration
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function restoreConfiguration(): Configuration
    {
        $configuration = Configuration::make();
        if (session()->has(Constants::CONFIGURATION)) {
            $configuration = Configuration::fromArray(session()->get(Constants::CONFIGURATION) ?? []);
        }
        // the config in the session will miss important values, we must get those from disk:
        // 'mapping', 'do_mapping', 'roles' are missing.
        $configFileName = session()->get(Constants::UPLOAD_CONFIG_FILE);
        if (null !== $configFileName) {
            $diskArray  = json_decode(StorageService::getContent($configFileName), true);
            $diskConfig = Configuration::fromArray($diskArray ?? []);

            $configuration->setMapping($diskConfig->getMapping());
            $configuration->setDoMapping($diskConfig->getDoMapping());
            $configuration->setRoles($diskConfig->getRoles());
        }
        return $configuration;
    }
}
