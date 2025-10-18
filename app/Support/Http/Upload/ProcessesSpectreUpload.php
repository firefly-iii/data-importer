<?php
/*
 * ProcessesSpectreUpload.php
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

namespace App\Support\Http\Upload;

use App\Events\ProvidedConfigUpload;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Storage\StorageService;
use Illuminate\Support\Facades\Log;

trait ProcessesSpectreUpload
{
    protected function processSpectreUpload(Configuration $configuration)
    {

        Log::debug('Save config to disk after processing Lunch Flow.');
        session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
        $configFileName = StorageService::storeContent((string)json_encode($configuration->toArray(), JSON_PRETTY_PRINT));

        event(new ProvidedConfigUpload($configFileName, $configuration));

        return redirect(route('011-connections.index'));

    }
}
