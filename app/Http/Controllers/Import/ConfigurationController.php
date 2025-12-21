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
use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ConfigurationControllerMiddleware;
use App\Http\Request\ConfigurationPostRequest;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\CSV\Converter\Date;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\SimpleFIN\Validation\ConfigurationContractValidator;
use App\Services\Storage\StorageService;
use App\Support\Http\RestoresConfiguration;
use App\Support\Internal\CollectsAccounts;
use App\Support\Internal\MergesAccountLists;
use Carbon\Carbon;
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

    private ImportJobRepository $repository;

    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Configuration');
        $this->middleware(ConfigurationControllerMiddleware::class);
        $this->repository = new ImportJobRepository();
    }

    public function index(Request $request, string $identifier)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $mainTitle = 'Configuration';
        $subTitle  = 'Configure your import';
        $doParse   = 'true' === $request->get('parse');
        $importJob = $this->repository->find($identifier);

        // if the job is "loaded", redirect to a step that will fix this, and show the user an intermediate page.
        if (!$doParse && 'loaded' === $importJob->getState()) {
            return view('import.004-configure.parsing')->with(compact('mainTitle', 'subTitle', 'identifier'));
        }
        // if the job is "loaded", parse it. Redirect if errors occur.
        if ($doParse && 'loaded' === $importJob->getState()) {
            $messages = $this->repository->parseImportJob($importJob);
            if ($messages->count() > 0) {
                return redirect()->route('new-import.index', [$importJob->getFlow()])->withErrors($messages);
            }

        }

        // if configuration says to skip this configuration step, skip it:
        $configuration = $importJob->getConfiguration();
        $doNotSkip     = 'true' === $request->get('do_not_skip');
        if (true === $configuration->isSkipForm() && false === $doNotSkip) {
            return view('import.004-configure.skipping')->with(compact('mainTitle', 'subTitle', 'identifier'));
        }

        $flow     = $importJob->getFlow();
        $camtType = '';
        // unique column options (this depends on the flow):
        $uniqueColumns       = config(sprintf('%s.unique_column_options', $flow));
        $applicationAccounts = $importJob->getApplicationAccounts();
        $currencies          = $importJob->getCurrencies();

        // TODO what is "importerAccounts" in this context?
        $importerAccounts = [];


        return view('import.004-configure.index', compact('camtType', 'mainTitle', 'subTitle', 'applicationAccounts', 'configuration', 'flow', 'camtType', 'importerAccounts', 'uniqueColumns', 'currencies'));
    }

    public function phpDate(Request $request): JsonResponse
    {
        Log::debug(sprintf('Method %s', __METHOD__));

        $dateObj = new Date();
        [$locale, $format] = $dateObj->splitLocaleFormat((string)$request->get('format'));

        /** @var Carbon $date */
        $date = today()->locale($locale);

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
            $validator = new ConfigurationContractValidator();

            // Validate form structure first
            $formValidation = $validator->validateFormFieldStructure($fromRequest);
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
