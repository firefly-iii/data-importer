<?php

declare(strict_types=1);
/*
 * NewJobDataCollector.php
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

namespace App\Services\Sophtron\Validation;

use App\Models\ImportJob;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Validation\NewJobDataCollectorInterface;
use App\Services\Sophtron\Model\Institution;
use App\Services\Sophtron\Request\GetInstitutionsRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;

class NewJobDataCollector implements NewJobDataCollectorInterface
{
    private ImportJob $importJob;

    public function getFlowName(): string
    {
        return 'sophtron';
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function setImportJob(ImportJob $importJob): void
    {
        $importJob->refreshInstanceIdentifier();
        $this->importJob = $importJob;
    }

    public function validate(): MessageBag
    {
        return new MessageBag();
    }

    public function collectAccounts(): MessageBag
    {
        return new MessageBag();
    }

    public function downloadInstitutions(): void
    {
        Log::debug('Now in downloadInstitutions()');
        $count     = count($this->importJob->getSophtronInstitutions());
        if (0 !== $count) {
            Log::debug(sprintf('There are %d institutions already, do not download.', $count));

            return;
        }
        $userId    = SecretManager::getSophtronUserId($this->importJob);
        $accessKey = SecretManager::getSophtronAccessKey($this->importJob);

        $request   = new GetInstitutionsRequest($userId, $accessKey);
        $response  = $request->get();
        $array     = [];
        foreach ($response as $country) {
            /** @var Institution $institution */
            foreach ($country['institutions'] as $institution) {
                $array[$country['country_code']][] = $institution->toArray();
            }
        }
        $this->importJob->setSophtronInstitutions($array);
    }
}
