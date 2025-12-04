<?php

/*
 * ConfigurationController.php
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

namespace App\Http\Controllers\Import;

use App\Events\CompletedConfiguration;
use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ConfigurationControllerMiddleware;
use App\Http\Request\ConfigurationPostRequest;
use App\Services\CSV\Converter\Date;
use App\Services\CSV\Mapper\TransactionCurrencies;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\File\FileContentSherlock;
use App\Services\Shared\Http\AccountListCollector;
use App\Services\SimpleFIN\Validation\ConfigurationContractValidator;
use App\Services\Storage\StorageService;
use App\Support\Http\RestoresConfiguration;
use App\Support\Internal\CollectsAccounts;
use App\Support\Internal\MergesAccountLists;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JsonException;

class ConfigurationController extends Controller
{
    use CollectsAccounts;
    use MergesAccountLists;
    use RestoresConfiguration;

    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Configuration');
        $this->middleware(ConfigurationControllerMiddleware::class);
    }

    public function index(Request $request)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $mainTitle          = 'Configuration';
        $subTitle           = 'Configure your import';
        $flow               = $request->cookie(Constants::FLOW_COOKIE);
        $configuration      = $this->restoreConfiguration();
        $camtType='';

        // if config says to skip it, skip it:
        $overruleSkip       = 'true' === $request->get('overruleskip');
        if (true === $configuration->isSkipForm() && false === $overruleSkip) {
            Log::debug('Skip configuration, go straight to the next step.');
            // set config as complete.
            event(new CompletedConfiguration($configuration));

            // skipForm
            return redirect()->route('005-roles.index');
        }

        // collect Firefly III accounts
        // this function returns an array with keys 'assets' and 'liabilities', each containing an array of Firefly III accounts.
        $fireflyIIIAccounts = $this->getFireflyIIIAccounts();

        // unique column options:
        $uniqueColumns      = config(sprintf('%s.unique_column_options', $flow));

        // also get the importer service accounts, if any.
        $collector          = new AccountListCollector($configuration, $flow, $fireflyIIIAccounts);

        try {
            $importerAccounts = $collector->collect();
        } catch (AgreementExpiredException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

            // remove thing from configuration
            $configuration->clearRequisitions();

            // save configuration in session and on disk:
            session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
            $configFileName = StorageService::storeContent((string)json_encode($configuration->toArray(), JSON_PRETTY_PRINT));
            session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);

            // redirect to selection.
            return redirect()->route('009-selection.index');
        }

        if ('file' === $flow) {
            // detect content type and save to config object.
            $detector = new FileContentSherlock();
            $content  = StorageService::getContent(session()->get(Constants::UPLOAD_DATA_FILE), $configuration->isConversion());
            $fileType = $detector->detectContentTypeFromContent($content);
            $configuration->setContentType($fileType);
            if ('camt' === $fileType) {
                $camtType       = $detector->getCamtType();
                $configuration->setCamtType($camtType);
                // save configuration in session and on disk AGAIN:
                session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
                $configFileName = StorageService::storeContent((string)json_encode($configuration->toArray(), JSON_PRETTY_PRINT));
                session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);
            }
        }
        // Get currency data for account creation widget
        $currencies         = $this->getCurrencies();

        return view('import.004-configure.index', compact('mainTitle', 'subTitle', 'fireflyIIIAccounts', 'configuration', 'flow', 'camtType', 'importerAccounts', 'uniqueColumns', 'currencies'));
    }

    /**
     * Get available currencies from Firefly III for account creation
     */
    private function getCurrencies(): array
    {
        try {
            /** @var TransactionCurrencies $mapper */
            $mapper = app(TransactionCurrencies::class);

            return $mapper->getMap();
        } catch (Exception $e) {
            Log::error(sprintf('Failed to load currencies: %s', $e->getMessage()));

            return [];
        }
    }

    public function phpDate(Request $request): JsonResponse
    {
        Log::debug(sprintf('Method %s', __METHOD__));

        $dateObj           = new Date();
        [$locale, $format] = $dateObj->splitLocaleFormat((string)$request->get('format'));

        /** @var Carbon $date */
        $date              = today()->locale($locale);

        return response()->json(['result' => $date->translatedFormat($format)]);
    }

    /**
     * @throws ImporterErrorException
     */
    public function postIndex(ConfigurationPostRequest $request): RedirectResponse
    {
        Log::debug(sprintf('Now running %s', __METHOD__));
        $fromRequest   = $request->getAll();
        $configuration = Configuration::fromRequest($fromRequest);
        $configuration->setFlow($request->cookie(Constants::FLOW_COOKIE));

        // Store do_import selections in session for validation
        session()->put('do_import', $fromRequest['do_import'] ?? []);

        // Validate configuration contract for SimpleFIN
        if ('simplefin' === $configuration->getFlow()) {
            $validator          = new ConfigurationContractValidator();

            // Validate form structure first
            $formValidation     = $validator->validateFormFieldStructure($fromRequest);
            if (!$formValidation->isValid()) {
                Log::error('SimpleFIN form validation failed', $formValidation->getErrors());

                return redirect()->back()->withErrors($formValidation->getErrorMessages())->withInput();
            }

            // Validate complete configuration contract
            $contractValidation = $validator->validateConfigurationContract($configuration);
            if (!$contractValidation->isValid()) {
                Log::error('SimpleFIN configuration contract validation failed', $contractValidation->getErrors());

                return redirect()->back()->withErrors($contractValidation->getErrorMessages())->withInput();
            }

            if ($contractValidation->hasWarnings()) {
                Log::warning('SimpleFIN configuration contract warnings', $contractValidation->getWarnings());
            }

        }
        $configuration->updateDateRange();
        // Map data option is now user-selectable for SimpleFIN via checkbox

        try {
            $json = json_encode($configuration->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }
        StorageService::storeContent($json);

        session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());

        // set config as complete.
        event(new CompletedConfiguration($configuration));

        // always redirect to roles, even if this isn't the step yet
        // for nordigen, spectre, and simplefin, roles will be skipped right away.
        Log::debug('Redirect to roles');

        return redirect(route('005-roles.index'));
    }
}
