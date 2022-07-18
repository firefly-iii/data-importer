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


use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ConfigurationControllerMiddleware;
use App\Http\Request\ConfigurationPostRequest;
use App\Services\CSV\Converter\Date;
use App\Services\Session\Constants;
use App\Services\Storage\StorageService;
use App\Support\Http\GetsRemoteData;
use App\Support\Http\ProcessesConfigurations;
use App\Support\Http\RestoresConfiguration;
use App\Support\Http\ValidatesCombinations;
use Cache;
use Carbon\Carbon;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class ConfigurationController
 * TODO for spectre and nordigen duplicate detection is only on transaction id
 */
class ConfigurationController extends Controller
{
    use RestoresConfiguration, GetsRemoteData, ValidatesCombinations, ProcessesConfigurations;

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
     * @param Request $request
     * @return mixed
     * @throws ImporterErrorException
     * @throws ImporterHttpException
     * @throws ApiHttpException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function index(Request $request): mixed
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $this->validatesCombinations();

        $mainTitle           = 'Configuration';
        $overruleSkip        = 'true' === $request->get('overruleskip');
        $subTitle            = 'Configure your import(s)';
        $ff3Accounts         = $this->getFF3Accounts();
        $combinations        = session()->get(Constants::UPLOADED_COMBINATIONS);
        $singleConfiguration = session()->get(Constants::SINGLE_CONFIGURATION_SESSION);
        $data                = [];

        app('log')->debug(sprintf('Array has %d configuration(s)', count($combinations)));
        /** @var array $entry */
        foreach ($combinations as $index => $entry) {
            app('log')->debug(sprintf('[%d/%d] processing configuration.', ($index + 1), count($combinations)));
            $data[] = $this->preProcessConfiguration($entry, $ff3Accounts, $overruleSkip);
        }

        return view('import.004-configure.index', compact('mainTitle', 'subTitle', 'ff3Accounts', 'data', 'singleConfiguration'));
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function phpDate(Request $request): JsonResponse
    {
        app('log')->debug(sprintf('Method %s', __METHOD__));

        $dateObj = new Date;
        [$locale, $format] = $dateObj->splitLocaleFormat((string) $request->get('format'));
        $date = Carbon::make('1984-09-17')->locale($locale);

        return response()->json(['result' => $date->translatedFormat($format)]);
    }

    /**
     * TODO this post routine is too complex.
     *
     * @param ConfigurationPostRequest $request
     *
     * @return RedirectResponse
     * @throws ImporterErrorException
     */
    public function postIndex(ConfigurationPostRequest $request): RedirectResponse
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $this->validatesCombinations();

        $combinations = session()->get(Constants::UPLOADED_COMBINATIONS);
        $data         = $request->getAll();
        // loop each entry.
        if ($data['count'] !== count($data['configurations'])) {
            throw new ImporterErrorException('Unexpected miscount in configuration array.');
        }

        /**
         * @var int   $index
         * @var array $current
         */
        foreach ($data['configurations'] as $index => $current) {
            $combinations[$index]['config_location'] = $this->postProcessConfiguration($current, $combinations[$index]['config_location'] ?? null);
        }

        // set config as complete.
        session()->put(Constants::CONFIG_COMPLETE_INDICATOR, true);

        // at this point, nordigen is ready for data conversion.
        session()->put(Constants::READY_FOR_CONVERSION, true);

        // store the combinations:
        session()->put(Constants::UPLOADED_COMBINATIONS, $combinations);

        // always redirect to roles, even if this isn't the step yet
        // for nordigen and spectre, roles will be skipped right away.
        return redirect(route('005-roles.index'));


    }
}
