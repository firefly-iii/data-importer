<?php

/*
 * RestoresConfiguration.php
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

namespace App\Support\Http;

use App\Exceptions\ImporterErrorException;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Storage\StorageService;

trait RestoresConfiguration
{
    /**
     * Restore configuration from session and drive.
     * @deprecated
     */
    protected function restoreConfiguration(?string $flow = null): Configuration
    {
        throw new ImporterErrorException('Do not restore configuration.');
        $configuration  = Configuration::make();
        $hasConfig      = session()->has(Constants::CONFIGURATION);
        if ($hasConfig) {
            $configuration = Configuration::fromArray(session()->get(Constants::CONFIGURATION) ?? []);
        }
        if (!$hasConfig && null !== $flow && 'file' !== $flow) {
            $configuration->setDuplicateDetectionMethod('cell');
        }
        // the config in the session will miss important values, we must get those from disk:
        // 'mapping', 'do_mapping', 'roles' are missing.
        $configFileName = session()->get(Constants::UPLOAD_CONFIG_FILE);
        if (null !== $configFileName) {
            $diskArray  = json_decode(StorageService::getContent($configFileName), true);
            $diskConfig = Configuration::fromArray($diskArray ?? []);
            $configuration->setCamtType($diskConfig->getCamtType());
            $configuration->setMapping($diskConfig->getMapping());
            $configuration->setDoMapping($diskConfig->getDoMapping());
            $configuration->setRoles($diskConfig->getRoles());
        }

        return $configuration;
    }
}
