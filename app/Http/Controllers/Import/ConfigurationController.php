<?php
/*
 * ConfigurationController.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
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

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ConfigurationControllerMiddleware;
use App\Http\Request\ConfigurationPostRequest;
use App\Services\CSV\Converter\Date;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\File\FileContentSherlock;
use App\Services\Storage\StorageService;
use App\Support\Http\RestoresConfiguration;
use App\Support\Internal\CollectsAccounts;
use App\Support\Internal\MergesAccountLists;
use Carbon\Carbon;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Class ConfigurationController
 * TODO for spectre and nordigen duplicate detection is only on transaction id
 */
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

    /**
     * @return Factory|RedirectResponse|View
     */
    public function index(Request $request)
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $mainTitle          = 'Configuration';
        $subTitle           = 'Configure your import';
        $flow               = $request->cookie(Constants::FLOW_COOKIE); // TODO should be from configuration right
        $configuration      = $this->restoreConfiguration();

        // if config says to skip it, skip it:
        $overruleSkip       = 'true' === $request->get('overruleskip');
        if (true === $configuration->isSkipForm() && false === $overruleSkip) {
            app('log')->debug('Skip configuration, go straight to the next step.');
            // set config as complete.
            session()->put(Constants::CONFIG_COMPLETE_INDICATOR, true);
            if ('nordigen' === $configuration->getFlow() || 'spectre' === $configuration->getFlow()) {
                // at this point, nordigen is ready for data conversion.
                session()->put(Constants::READY_FOR_CONVERSION, true);
            }

            // skipForm
            return redirect()->route('005-roles.index');
        }

        // collect Firefly III accounts
        $fireflyIIIaccounts = $this->getFireflyIIIAccounts();

        // possibilities for duplicate detection (unique columns)

        // also get the nordigen / spectre accounts
        $importerAccounts   = [];
        $uniqueColumns      = config('csv.unique_column_options');
        if ('nordigen' === $flow) {
            // TODO here we need to redirect to Nordigen.
            try {
                $importerAccounts = $this->getNordigenAccounts($configuration);
            } catch (AgreementExpiredException $e) {
                app('log')->error($e->getMessage());

                // remove thing from configuration
                $configuration->clearRequisitions();

                // save configuration in session and on disk:
                session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
                $configFileName = StorageService::storeContent(json_encode($configuration->toArray(), JSON_PRETTY_PRINT));
                session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);

                // redirect to selection.
                return redirect()->route('009-selection.index');
            }
            $uniqueColumns    = config('nordigen.unique_column_options');
            $importerAccounts = $this->mergeNordigenAccountLists($importerAccounts, $fireflyIIIaccounts);
        }

        if ('spectre' === $flow) {
            $importerAccounts = $this->getSpectreAccounts($configuration);
            $uniqueColumns    = config('spectre.unique_column_options');
            $importerAccounts = $this->mergeSpectreAccountLists($importerAccounts, $fireflyIIIaccounts);
        }

        if ('file' === $flow) {
            // detect content type and save to config object.
            $detector = new FileContentSherlock();
            $content  = StorageService::getContent(session()->get(Constants::UPLOAD_DATA_FILE), $configuration->isConversion());
            $fileType = $detector->detectContentTypeFromContent($content);
            $configuration->setContentType($fileType);
        }

        return view(
            'import.004-configure.index',
            compact('mainTitle', 'subTitle', 'fireflyIIIaccounts', 'configuration', 'flow', 'importerAccounts', 'uniqueColumns')
        );
    }

    public function phpDate(Request $request): JsonResponse
    {
        app('log')->debug(sprintf('Method %s', __METHOD__));

        $dateObj           = new Date();
        [$locale, $format] = $dateObj->splitLocaleFormat((string)$request->get('format'));
        $date              = Carbon::make('1984-09-17')->locale($locale);

        return response()->json(['result' => $date->translatedFormat($format)]);
    }

    /**
     * @throws ImporterErrorException
     */
    public function postIndex(ConfigurationPostRequest $request): RedirectResponse
    {
        app('log')->debug(sprintf('Now running %s', __METHOD__));
        // store config on drive.v
        $fromRequest   = $request->getAll();
        $configuration = Configuration::fromRequest($fromRequest);
        $configuration->setFlow($request->cookie(Constants::FLOW_COOKIE));

        // TODO are all fields actually in the config?

        // loop accounts:
        $accounts      = [];
        foreach (array_keys($fromRequest['do_import']) as $identifier) {
            if (array_key_exists($identifier, $fromRequest['accounts'])) {
                $accounts[$identifier] = (int)$fromRequest['accounts'][$identifier];
            }
        }
        $configuration->setAccounts($accounts);
        $configuration->updateDateRange();

        $json          = '{}';

        try {
            $json = json_encode($configuration->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (\JsonException $e) {
            app('log')->error($e->getMessage());

            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }
        StorageService::storeContent($json);

        session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());

        app('log')->debug(sprintf('Configuration debug: Connection ID is "%s"', $configuration->getConnection()));
        // set config as complete.
        session()->put(Constants::CONFIG_COMPLETE_INDICATOR, true);
        if ('nordigen' === $configuration->getFlow() || 'spectre' === $configuration->getFlow()) {
            // at this point, nordigen is ready for data conversion.
            session()->put(Constants::READY_FOR_CONVERSION, true);
        }

        // always redirect to roles, even if this isn't the step yet
        // for nordigen and spectre, roles will be skipped right away.
        return redirect(route('005-roles.index'));
    }
}
