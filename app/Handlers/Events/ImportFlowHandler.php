<?php

namespace App\Handlers\Events;

use App\Events\CompletedConfiguration;
use App\Events\ProvidedConfigUpload;
use App\Events\ProvidedDataUpload;
use App\Services\Session\Constants;
use Illuminate\Support\Facades\Log;

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
        if('' !== $event->fileName){
            session()->put(Constants::UPLOAD_DATA_FILE, $event->fileName);
        }
        session()->put(Constants::HAS_UPLOAD, true);
    }

    public function handleProvidedConfigUpload(ProvidedConfigUpload $event): void
    {
        if('' !== $event->fileName) {
            session()->put(Constants::UPLOAD_CONFIG_FILE, $event->fileName);
        }
        if('nordigen' !== $event->configuration->getFlow()) {
            // at this point, every flow exept the GoCardless flow will pretend to have selected a country and a bank.
            Log::debug('Marking country and bank as selected for non-GoCardless flow.');
            session()->put(Constants::SELECTED_BANK_COUNTRY, true);
        }
        if('simplefin' !== $event->configuration->getFlow()) {
            Log::debug('Mark Simplefin account data as present for non-Simplefin flow.');
            session()->put(Constants::SIMPLEFIN_ACCOUNTS_DATA, true);
        }
        session()->put(Constants::HAS_UPLOAD, true);
    }
}
