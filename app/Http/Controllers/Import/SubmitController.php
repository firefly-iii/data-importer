<?php
/*
 * SubmitController.php
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

namespace App\Http\Controllers\Import;

use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ReadyForImport;
use App\Services\CSV\Configuration\Configuration;
use App\Services\Session\Constants;
use App\Services\Shared\Conversion\ConversionStatus;
use App\Services\Shared\Conversion\RoutineStatusManager;
use App\Services\Shared\Import\Status\SubmissionStatus;
use App\Services\Shared\Import\Status\SubmissionStatusManager;
use App\Services\Storage\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Log;


/**
 * Class SubmitController
 */
class SubmitController extends Controller
{
    protected const DISK_NAME = 'jobs';

    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Importing data...');
        $this->middleware(ReadyForImport::class);
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function index()
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        $mainTitle = 'Submit the data';

        // get configuration object.
        $configuration = Configuration::fromArray(session()->get(Constants::CONFIGURATION));
        // append info from the file on disk:
        $configFileName = Constants::UPLOAD_CONFIG_FILE;
        if (null !== $configFileName) {
            $diskArray  = json_decode(StorageService::getContent(session()->get($configFileName)), true, JSON_THROW_ON_ERROR);
            $diskConfig = Configuration::fromArray($diskArray);
            $configuration->setDoMapping($diskConfig->getDoMapping());
            $configuration->setMapping($diskConfig->getMapping());
        }
        $jobBackUrl = route('back.conversion');

        // job ID may be in session:
        $identifier = session()->get(Constants::CONVERSION_JOB_IDENTIFIER);
        $flow       = $configuration->getFlow();

        // validate flow
        if (!in_array($flow, config('importer.flows'), true)) {
            throw new ImporterErrorException(sprintf('Not a supported flow: "%s"', $flow));
        }

        Log::debug(sprintf('Submit (import) routine manager identifier is "%s"', $identifier));

        // store identifier in session so the status can get it.
        session()->put(Constants::IMPORT_JOB_IDENTIFIER, $identifier);
        Log::debug(sprintf('Stored "%s" under "%s"', $identifier, Constants::IMPORT_JOB_IDENTIFIER));

        return view('import.008-submit.index', compact('mainTitle', 'identifier', 'jobBackUrl'));
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @throws JsonException
     */
    public function status(Request $request): JsonResponse
    {

        $identifier = $request->get('identifier');
        Log::debug(sprintf('Now at %s(%s)', __METHOD__, $identifier));
        if (null === $identifier) {
            Log::warning('Identifier is NULL.');
            // no status is known yet because no identifier is in the session.
            // As a fallback, return empty status
            $fakeStatus = new SubmissionStatus;

            return response()->json($fakeStatus->toArray());
        }
        $importJobStatus = SubmissionStatusManager::startOrFindSubmission($identifier);

        return response()->json($importJobStatus->toArray());
    }

}
