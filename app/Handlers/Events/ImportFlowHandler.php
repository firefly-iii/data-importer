<?php

namespace App\Handlers\Events;

use App\Events\CompletedConfiguration;
use App\Events\ProvidedConfigUpload;
use App\Events\ProvidedDataUpload;
use App\Services\Session\Constants;

class ImportFlowHandler
{

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
        session()->put(Constants::UPLOAD_DATA_FILE, $event->fileName);
        session()->put(Constants::HAS_UPLOAD, true);
    }

    public function handleProvidedConfigUpload(ProvidedConfigUpload $event): void
    {
        session()->put(Constants::UPLOAD_CONFIG_FILE, $event->fileName);
        session()->put(Constants::HAS_UPLOAD, true);
    }
}
