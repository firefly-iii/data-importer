<?php

declare(strict_types=1);
/*
 * ImportFlowHandler.php
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

namespace App\Handlers\Events;

use App\Events\CompletedConfiguration;
use App\Events\DownloadedSimpleFINAccounts;
use App\Events\ProvidedConfigUpload;
use App\Events\ProvidedDataUpload;
use App\Services\Session\Constants;
use Illuminate\Support\Facades\Log;

class ImportFlowHandler
{

    public function handleDownloadedSimpleFINAccounts(DownloadedSimpleFINAccounts $event): void {

    }
    public function handleCompletedConfiguration(CompletedConfiguration $event): void
    {
        $configuration = $event->configuration;
        session()->put(Constants::CONFIG_COMPLETE_INDICATOR, true);
        if ('lunchflow' === $configuration->getFlow() || 'nordigen' === $configuration->getFlow() || 'spectre' === $configuration->getFlow() || 'simplefin' === $configuration->getFlow()) {
            // at this point, nordigen, spectre, and simplefin are ready for data conversion.
            session()->put(Constants::READY_FOR_CONVERSION, true);
        }
    }

    public function handleProvidedDataUpload(ProvidedDataUpload $event): void
    {
        if ('' !== $event->fileName) {
            session()->put(Constants::UPLOAD_DATA_FILE, $event->fileName);
        }
        session()->put(Constants::HAS_UPLOAD, true);
    }

    public function handleProvidedConfigUpload(ProvidedConfigUpload $event): void
    {
        if ('' !== $event->fileName) {
            session()->put(Constants::UPLOAD_CONFIG_FILE, $event->fileName);
        }
        if ('nordigen' !== $event->configuration->getFlow()) {
            // at this point, every flow exept the GoCardless flow will pretend to have selected a country and a bank.
            Log::debug('Marking country and bank as selected for non-GoCardless flow.');
            session()->put(Constants::SELECTED_BANK_COUNTRY, true);
        }
        if ('simplefin' !== $event->configuration->getFlow()) {
            Log::debug('Mark Simplefin account data as present for non-Simplefin flow.');
            session()->put(Constants::SIMPLEFIN_ACCOUNTS_DATA, true);
        }
        session()->put(Constants::HAS_UPLOAD, true);
    }
}
