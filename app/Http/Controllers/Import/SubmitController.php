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

declare(strict_types=1);

namespace App\Http\Controllers\Import;

use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SubmitControllerMiddleware;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Import\Routine\RoutineManager;
use App\Services\Shared\Import\Status\SubmissionStatus;
use App\Services\Shared\Import\Status\SubmissionStatusManager;
use App\Services\Storage\StorageService;
use App\Support\Http\RestoresConfiguration;
use App\Support\Http\ValidatesCombinations;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Storage;

/**
 * Class SubmitController
 */
class SubmitController extends Controller
{
    use RestoresConfiguration, ValidatesCombinations;

    protected const DISK_NAME = 'jobs';


    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        view()->share('pageTitle', 'Submit data to Firefly III');
        $this->middleware(SubmitControllerMiddleware::class);
    }

    /**
     * @return Application|Factory|View
     * @throws ContainerExceptionInterface
     * @throws ImporterErrorException
     * @throws NotFoundExceptionInterface
     */
    public function index()
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $mainTitle = 'Submit the data';
        $this->validatesCombinations();
        $combinations = session()->get(Constants::UPLOADED_COMBINATIONS);
        $jobBackUrl   = route('back.conversion');

        // this routine recycles the conversion identifier.

        /**
         * @var int   $index
         * @var array $combination
         */
        foreach ($combinations as $index => $combination) {
            // create configuration:
            $configuration = Configuration::fromArray(json_decode(StorageService::getContent($combination['config_location']), true));
            $flow          = $configuration->getFlow();

            // validate flow
            if (!in_array($flow, config('importer.flows'), true)) {
                throw new ImporterErrorException(sprintf('Not a supported flow: "%s"', $flow));
            }
        }
        return view('import.008-submit.index', compact('mainTitle', 'combinations', 'jobBackUrl'));
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function start(Request $request): JsonResponse
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $identifier = $request->get('identifier');
        if (null === $identifier) {
            app('log')->error('Identifier is NULL');
            $status         = new SubmissionStatus;
            $status->status = SubmissionStatus::SUBMISSION_ERRORED;
            return response()->json($status->toArray());
        }
        $configuration   = $this->restoreConfiguration();
        $routine         = new RoutineManager($identifier);
        $importJobStatus = SubmissionStatusManager::startOrFindSubmission($identifier);
        $disk            = Storage::disk(self::DISK_NAME);
        $fileName        = sprintf('%s.json', $identifier);

        // get files from disk:
        if (!$disk->has($fileName)) {
            // TODO error in logs
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED, $identifier);
            return response()->json($importJobStatus->toArray());
        }

        try {
            $json         = $disk->get($fileName);
            $transactions = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            app('log')->debug(sprintf('Found %d transactions on the drive.', count($transactions)));
        } catch (FileNotFoundException|JsonException $e) {
            // TODO error in logs
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED, $identifier);
            return response()->json($importJobStatus->toArray());
        }

        $routine->setTransactions($transactions);

        SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_RUNNING, $identifier);

        // then push stuff into the routine:
        $routine->setConfiguration($configuration);
        try {
            $routine->start();
        } catch (ImporterErrorException $e) {
            app('log')->error($e->getMessage());
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED, $identifier);
            return response()->json($importJobStatus->toArray());
        }

        // set done:
        SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_DONE, $identifier);

        // set config as complete.
        session()->put(Constants::SUBMISSION_COMPLETE_INDICATOR, true);

        return response()->json($importJobStatus->toArray());
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {

        $identifier = $request->get('identifier');
        app('log')->debug(sprintf('Now at %s(%s)', __METHOD__, $identifier));
        if (null === $identifier) {
            app('log')->warning('Identifier is NULL.');
            // no status is known yet because no identifier is in the session.
            // As a fallback, return empty status
            $fakeStatus = new SubmissionStatus;

            return response()->json($fakeStatus->toArray());
        }
        $importJobStatus = SubmissionStatusManager::startOrFindSubmission($identifier);

        return response()->json($importJobStatus->toArray());
    }

}
