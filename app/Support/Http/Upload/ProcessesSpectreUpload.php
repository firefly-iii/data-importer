<?php

namespace App\Support\Http\Upload;

use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Storage\StorageService;
use Illuminate\Support\Facades\Log;

trait ProcessesSpectreUpload
{
    protected function processSpectreUpload(Configuration $configuration)
    {
        // redirect to spectre
        session()->put(Constants::HAS_UPLOAD, true);

        Log::debug('Save config to disk after processing Lunch Flow.');
        session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
        $configFileName = StorageService::storeContent((string)json_encode($configuration->toArray(), JSON_PRETTY_PRINT));
        session()->put(Constants::HAS_UPLOAD, true);
        session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);

        return redirect(route('011-connections.index'));

    }
}
