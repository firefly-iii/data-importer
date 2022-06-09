<?php
/*
 * ConversionController.php
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
use App\Http\Middleware\ConversionControllerMiddleware;
use App\Services\CSV\Conversion\RoutineManager as CSVRoutineManager;
use App\Services\Nordigen\Conversion\RoutineManager as NordigenRoutineManager;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ConversionStatus;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\Shared\Conversion\RoutineStatusManager;
use App\Services\Spectre\Conversion\RoutineManager as SpectreRoutineManager;
use App\Services\Storage\StorageService;
use App\Support\Http\RestoresConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use JsonException;

/**
 * Class ConversionController
 */
class ConversionController extends Controller
{
    use RestoresConfiguration;

    protected const DISK_NAME = 'jobs'; // TODO stored in several places

    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Importing data...');
        $this->middleware(ConversionControllerMiddleware::class);
    }

    /**
     *
     */
    public function index()
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $mainTitle         = 'Convert the data';
        $jobBackUrl        = route('back.mapping');
        $nextUrl           = route('008-submit.index');
        $hasExternalImport = false;

        // grab configs from session
        // TODO move to helper
        $combinations = session()->get(Constants::UPLOADED_COMBINATIONS);
        if (!is_array($combinations)) {
            throw new ImporterErrorException('Combinations must be an array.');
        }
        if (count($combinations) < 1) {
            throw new ImporterErrorException('Combinations must be more than zero.');
        }
        /**
         * @var int   $index
         * @var array $combination
         */
        foreach ($combinations as $index => $combination) {
            // create configuration:
            $object     = Configuration::fromArray(json_decode(StorageService::getContent($combination['config_location']), true));
            $identifier = $combination['conversion_identifier'] ?? null;
            $flow       = $object->getFlow();

            // switch based on flow:
            if (!in_array($flow, config('importer.flows'), true)) {
                app('log')->error(sprintf('Not a supported flow: "%s"', $flow));
                continue;
            }
            /** @var RoutineManagerInterface $routine */
            if ('file' === $flow) {
                // TODO needs a file check here
                app('log')->debug('Create CSV routine manager.');
                $routine = new CSVRoutineManager($identifier);
            }
            if ('nordigen' === $flow) {
                app('log')->debug('Create Nordigen routine manager.');
                $routine = new NordigenRoutineManager($identifier);
            }
            if ('spectre' === $flow) {
                app('log')->debug('Create Spectre routine manager.');
                $routine = new SpectreRoutineManager($identifier);
            }
            if ($object->isMapAllData() && in_array($flow, ['spectre', 'nordigen'], true)) {
                $hasExternalImport = true;
            }
            // may be a new identifier! Yay!
            $combinations[$index]['flow']                  = $flow;
            $combinations[$index]['conversion_identifier'] = $routine->getIdentifier();
            app('log')->debug(sprintf('Conversion routine manager identifier is "%s"', $combinations[$index]['conversion_identifier']));
        }

        if ($hasExternalImport) {
            app('log')->debug('Will redirect to mapping after conversion.');
            $nextUrl = route('006-mapping.index');
        }
        session()->put(Constants::UPLOADED_COMBINATIONS, $combinations);

        // store identifier in session so the status can get it.
        return view('import.007-convert.index', compact('mainTitle', 'combinations', 'jobBackUrl', 'nextUrl'));
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @throws ImporterErrorException
     */
    public function start(Request $request): JsonResponse
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $identifier = $request->get('identifier');

        // find configuration among session details:
        // TODO move to helper
        $combinations = session()->get(Constants::UPLOADED_COMBINATIONS);
        if (!is_array($combinations)) {
            throw new ImporterErrorException('Combinations must be an array.');
        }
        if (count($combinations) < 1) {
            throw new ImporterErrorException('Combinations must be more than zero.');
        }
        app('log')->debug('Combinations', $combinations);
        $set = null;
        foreach ($combinations as $combination) {
            if ($combination['conversion_identifier'] === $identifier) {
                $set = $combination;
            }
        }
        if (null === $set) {
            throw new ImporterErrorException(sprintf('Could not find set "%s"!', $identifier));
        }

        // from array this time.
        $object = Configuration::fromArray(json_decode(StorageService::getContent($set['config_location']), true));
        $flow   = $object->getFlow();

        if (!in_array($flow, config('importer.flows'), true)) {
            throw new ImporterErrorException(sprintf('Not a supported flow: "%s"', $flow));
        }
        /** @var RoutineManagerInterface $routine */
        if ('file' === $flow) {
            $disk = Storage::disk('uploads');
            $routine = new CSVRoutineManager($identifier);
            $routine->setContent($disk->get($set['storage_location']));
        }
        if ('nordigen' === $flow) {
            $routine = new NordigenRoutineManager($identifier);
        }
        if ('spectre' === $flow) {
            $routine = new SpectreRoutineManager($identifier);
        }

        $importJobStatus = RoutineStatusManager::startOrFindConversion($identifier);

        RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_RUNNING, $identifier);
        sleep(10);
        // then push stuff into the routine:
        $routine->setConfiguration($object);
        try {
            $transactions = $routine->start();
        } catch (ImporterErrorException $e) {
            app('log')->error($e->getMessage());
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_ERRORED, $identifier);
            return response()->json($importJobStatus->toArray());
        }
        app('log')->debug(sprintf('Conversion routine "%s" was started successfully.', $flow));
        if (0 === count($transactions)) {
            app('log')->error('Zero transactions!');
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_ERRORED, $identifier);
            return response()->json($importJobStatus->toArray());
        }
        app('log')->debug(sprintf('Conversion routine "%s" yielded %d transaction(s).', $flow, count($transactions)));
        // save transactions in 'jobs' directory under the same key as the conversion thing.
        $disk = Storage::disk(self::DISK_NAME);
        try {
            $disk->put(sprintf('%s.json', $identifier), json_encode($transactions, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            app('log')->error(sprintf('JSON exception: %s', $e->getMessage()));
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_ERRORED, $identifier);
            return response()->json($importJobStatus->toArray());
        }
        app('log')->debug(sprintf('Transactions are stored on disk "%s" in file "%s.json"', self::DISK_NAME, $identifier));


        // set done:
        RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_DONE, $identifier);

        // set config as complete.
        session()->put(Constants::CONVERSION_COMPLETE_INDICATOR, true);
        app('log')->debug('Set conversion as complete.');

        return response()->json($importJobStatus->toArray());
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $identifier = $request->get('identifier');
        app('log')->debug(sprintf('Now at %s(%s)', __METHOD__, $identifier));
        if (null === $identifier) {
            app('log')->warning('Identifier is NULL.');
            // no status is known yet because no identifier is in the session.
            // As a fallback, return empty status
            $fakeStatus = new ConversionStatus;

            return response()->json($fakeStatus->toArray());
        }
        $importJobStatus = RoutineStatusManager::startOrFindConversion($identifier);

        return response()->json($importJobStatus->toArray());
    }
}
