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
use App\Http\Middleware\SubmitControllerMiddleware;
use App\Services\CSV\Configuration\Configuration;
use App\Services\Session\Constants;
use App\Services\Shared\Import\Routine\RoutineManager;
use App\Services\Shared\Import\Status\SubmissionStatus;
use App\Services\Shared\Import\Status\SubmissionStatusManager;
use App\Services\Storage\StorageService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Log;
use Storage;

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
        $this->middleware(SubmitControllerMiddleware::class);
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
        if (null !== session()->get($configFileName)) {
            $diskArray  = json_decode(StorageService::getContent($configFileName), true, JSON_THROW_ON_ERROR);
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

    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @throws ImporterErrorException
     */
    public function start(Request $request): JsonResponse
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $identifier        = $request->get('identifier');
        $configuration     = Configuration::fromArray(session()->get(Constants::CONFIGURATION));
        $configurationFile = session()->get(Constants::UPLOAD_CONFIG_FILE);
        $routine           = new RoutineManager($identifier);
        $importJobStatus   = SubmissionStatusManager::startOrFindSubmission($identifier);
        $disk              = Storage::disk(self::DISK_NAME);
        $fileName          = sprintf('%s.json', $identifier);

        if (null !== $configurationFile) {
            $diskArray  = json_decode(StorageService::getContent($configurationFile), true, JSON_THROW_ON_ERROR);
            $diskConfig = Configuration::fromArray($diskArray);
            $configuration->setMapping($diskConfig->getMapping());
            $configuration->setDoMapping($diskConfig->getDoMapping());
            $configuration->setRoles($diskConfig->getRoles());
        }

        // get files from disk:
        if (!$disk->has($fileName)) {
            // TODO error in logs
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED);
            return response()->json($importJobStatus->toArray());
        }

        try {
            $json = $disk->get($fileName);
            $transactions = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            Log::debug(sprintf('Found %d transactions on the drive.', count($transactions)));
        } catch (FileNotFoundException|JsonException $e) {
            // TODO error in logs
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED);
            return response()->json($importJobStatus->toArray());
        }

        $routine->setTransactions($transactions);

        SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_RUNNING);

        // then push stuff into the routine:
        $routine->setConfiguration($configuration);
        try {
            $routine->start();
        } catch (ImporterErrorException $e) {
            Log::error($e->getMessage());
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED);
            return response()->json($importJobStatus->toArray());
        }

        // set done:
        SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_DONE);

        // set config as complete.
        session()->put(Constants::SUBMISSION_COMPLETE_INDICATOR, true);


//            // if configured, send report!
//            // TODO make event handler.
//            $log
//                = [
//                'messages' => $routine->getAllMessages(),
//                'warnings' => $routine->getAllWarnings(),
//                'errors'   => $routine->getAllErrors(),
//            ];
//
//            $send = config('mail.enable_mail_report');
//            Log::debug('Log log', $log);
//            if (true === $send) {
//                Log::debug('SEND MAIL');
//                Mail::to(config('mail.destination'))->send(new ImportFinished($log));
//            }


        return response()->json($importJobStatus->toArray());
    }

}
