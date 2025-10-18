<?php

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
