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

use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Request\ConfigurationPostRequest;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\CSV\Converter\Date;
use App\Services\Shared\Model\ImportServiceAccount;
use App\Support\Internal\CollectsAccounts;
use App\Support\Internal\MergesAccountLists;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConfigurationController extends Controller
{
    use CollectsAccounts;
    use MergesAccountLists;

    private ImportJobRepository $repository;

    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Configuration');
        $this->repository = new ImportJobRepository();
    }

    public function index(Request $request, string $identifier)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $mainTitle           = 'Configuration';
        $subTitle            = 'Configure your import';
        $doParse             = 'true' === $request->get('parse');
        $importJob           = $this->repository->find($identifier);
        $flow                = $importJob->getFlow();

        Log::debug(sprintf('Import job state is "%s", doParse is %s', $importJob->getState(), var_export($doParse, true)));

        // if the job is "contains_content", redirect to a step that will fix this, and show the user an intermediate page.
        if (!$doParse && 'contains_content' === $importJob->getState()) {

            // extra check for Sophtron, if it has no institutions or accounts.
            if ('sophtron' === $flow && 0 === count($importJob->getServiceAccounts())) {
                return view('import.004-configure.sophtron-needs-institutions')->with(compact('mainTitle', 'subTitle', 'identifier'));
            }

            return view('import.004-configure.parsing')->with(compact('mainTitle', 'subTitle', 'identifier'));
        }
        // if the job is "contains_content", parse it. Redirect if errors occur.
        if ($doParse && 'contains_content' === $importJob->getState()) {
            // FIXME this routine is the same as in AutoImports::importFileAsImportJob
            $messages = $this->repository->parseImportJob($importJob);

            if ($messages->count() > 0) {
                // missing_requisitions
                // if the job has no requisitions (Nordigen!) need to redirect to get some?
                if ($messages->has('missing_requisitions') && 'true' === (string)$messages->get('missing_requisitions')[0]) {
                    $importJob->setState('needs_connection_details');
                    $this->repository->saveToDisk($importJob);

                    return redirect()->route('select-bank.index', [$identifier]);
                }
                if ($messages->has('expired_agreement') && 'true' === (string)$messages->get('expired_agreement')[0]) {
                    $importJob->setServiceAccounts([]);
                    $configuration = $importJob->getConfiguration();
                    $configuration->clearRequisitions();
                    $importJob->setConfiguration($configuration);
                    $importJob->setState('needs_connection_details');
                    $this->repository->saveToDisk($importJob);
                    $redirect      = route('select-bank.index', [$identifier]);

                    return view('import.004-configure.gocardless-expired')->with(compact('mainTitle', 'subTitle', 'redirect'));

                }
                //
                // if the agreement has expired, show error and exit gracefully.
                // https://firefly-data.hades.internal/configure-import/2385f86b-0e50-4ba7-8b7c-e663471f2dd6?parse=true

                // if there is any state for the job here forget about it, just remove it.
                $this->repository->deleteImportJob($importJob);

                return redirect()->route('new-import.index', [$flow])->withErrors($messages);
            }
        }
        $importJob->setInitialized(true);
        $this->repository->saveToDisk($importJob);

        // if configuration says to skip this configuration step, skip it:
        $configuration       = $importJob->getConfiguration();
        $doNotSkip           = 'true' === $request->get('do_not_skip');
        if (true === $configuration->isSkipForm() && false === $doNotSkip) {
            // FIXME must also skip roles and mapping.
            $redirect = $this->redirectToNextstep($importJob);

            return view('import.004-configure.skipping')->with(compact('mainTitle', 'subTitle', 'identifier', 'redirect'));
        }

        // unique column options (this depends on the flow):
        $uniqueColumns       = config(sprintf('%s.unique_column_options', $flow)) ?? [];
        $applicationAccounts = $importJob->getApplicationAccounts();
        $serviceAccounts     = $importJob->getServiceAccounts();
        $currencies          = $importJob->getCurrencies();
        $accounts            = $this->mergeAccountLists($flow, $applicationAccounts, $serviceAccounts);
        $camtType            = $configuration->getCamtType();

        return view('import.004-configure.index', compact('camtType', 'identifier', 'mainTitle', 'subTitle', 'applicationAccounts', 'configuration', 'flow', 'accounts', 'uniqueColumns', 'currencies'));
    }

    private function mergeAccountLists(string $flow, array $applicationAccounts, array $serviceAccounts): array
    {
        Log::debug(sprintf('Now running %s', __METHOD__));
        $generic = match ($flow) {
            'nordigen'  => ImportServiceAccount::convertNordigenArray($serviceAccounts),
            'simplefin' => ImportServiceAccount::convertSimpleFINArray($serviceAccounts),
            'lunchflow' => ImportServiceAccount::convertLunchflowArray($serviceAccounts),
            'sophtron'  => ImportServiceAccount::convertSophtronArray($serviceAccounts),
            'file'      => [],
            default     => throw new ImporterErrorException(sprintf('Cannot mergeAccountLists("%s")', $flow)),
        };

        return $this->mergeGenericAccountList($generic, $applicationAccounts);
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
    public function postIndex(ConfigurationPostRequest $request, string $identifier): RedirectResponse
    {
        Log::debug(sprintf('Now running %s', __METHOD__));

        // this creates a whole new configuration object. Not OK. Only need to update the necessary fields in the CURRENT request.
        $importJob     = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();
        $configuration->updateFromRequest($request->getAll());
        $configuration->updateDateRange();
        $importJob->setConfiguration($configuration);

        return redirect($this->redirectToNextstep($importJob));
    }

    private function redirectToNextstep(ImportJob $importJob): string
    {
        $importJob->setState('is_configured');
        $this->repository->saveToDisk($importJob);

        // at this moment the config should be valid and saved.
        // file import ONLY needs roles before it is complete. After completion, can go to overview.
        if ('file' === $importJob->getFlow()) {
            return route('configure-roles.index', [$importJob->identifier]);
        }

        // simplefin and others are now complete.
        $importJob->setState('configured_and_roles_defined');
        $this->repository->saveToDisk($importJob);

        // can now redirect to conversion, because that will be the next step.
        return route('data-conversion.index', [$importJob->identifier]);
    }
}
