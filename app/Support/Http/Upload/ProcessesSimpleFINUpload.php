<?php

declare(strict_types=1);
/*
 * ProcessesSimpleFINUpload.php
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
use App\Exceptions\ImporterErrorException;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\SimpleFIN\SimpleFINService;
use App\Services\Storage\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;

trait ProcessesSimpleFINUpload
{
    /**
     * Handle SimpleFIN flow integration
     */
    protected function processSimpleFIN(Request $request, Configuration $configuration): RedirectResponse
    {
        $errors           = new MessageBag();
        Log::debug('UploadController::handleSimpleFINFlow() INVOKED'); // Unique entry marker

        $setupToken       = (string)$request->get('simplefin_token');
        $isDemo           = $request->boolean('use_demo');
        $accessToken      = $configuration->getAccessToken();
        Log::debug(sprintf('handleSimpleFINFlow("%s")', $setupToken));

        if ($isDemo) {
            Log::debug('Overrule info with demo info.');
            $setupToken = (string)config('simplefin.demo_token');
        }
        if ('' === $setupToken && '' === $accessToken) {
            $errors->add('simplefin_token', 'SimpleFIN token is required.');
        }
        if ($errors->count() > 0) {
            Log::debug('Errors in SimpleFIN flow, return to upload form.');

            return redirect(route('003-upload.index'))->withErrors($errors)->withInput();
        }

        // Store data in session (may be empty).
        session()->put(Constants::SIMPLEFIN_TOKEN, $setupToken);

        // create service:
        /** @var SimpleFINService $simpleFINService */
        $simpleFINService = app(SimpleFINService::class);
        $simpleFINService->setSetupToken($setupToken);
        $simpleFINService->setConfiguration($configuration);
        $simpleFINService->setAccessToken($accessToken);

        try {
            // try to get an access token, if not already present in configuration.
            Log::debug('Will collect access token from simpleFIN using setup token.');
            $simpleFINService->exchangeSetupTokenForAccessToken();
            $accessToken = $simpleFINService->getAccessToken();
        } catch (ImporterErrorException $e) {
            Log::error('SimpleFIN connection failed, could not exchange token.', ['error' => $e->getMessage()]);
            $errors->add('connection', sprintf('Failed to connect to SimpleFIN: %s', $e->getMessage()));

            return redirect(route('003-upload.index'))->withErrors($errors)->withInput();
        }
        $configuration->setAccessToken($accessToken);

        try {
            $accountsData   = $simpleFINService->fetchAccounts();

            // save configuration in session and on disk: TODO needs a trait.
            Log::debug('Save config to disk after setting access token.');
            session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
            $configFileName = StorageService::storeContent((string)json_encode($configuration->toArray(), JSON_PRETTY_PRINT));

            // Store SimpleFIN data in session for configuration step
            session()->put(Constants::SIMPLEFIN_TOKEN, $accessToken);
            session()->put(Constants::SIMPLEFIN_ACCOUNTS_DATA, $accountsData);
            session()->put(Constants::SIMPLEFIN_IS_DEMO, $isDemo);

            event(new ProvidedConfigUpload($configFileName, $configuration));

            Log::info('SimpleFIN connection established', ['account_count' => count($accountsData), 'is_demo' => $isDemo]);

            return redirect(route('004-configure.index'));
        } catch (ImporterErrorException $e) {
            Log::error('SimpleFIN connection failed', ['error' => $e->getMessage()]);
            $errors->add('connection', sprintf('Failed to connect to SimpleFIN: %s', $e->getMessage()));

            return redirect(route('003-upload.index'))->withErrors($errors)->withInput();
        }
    }
}
