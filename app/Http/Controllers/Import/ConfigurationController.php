<?php
declare(strict_types=1);
/**
 * ConfigurationController.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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


use App\Http\Controllers\Controller;
use App\Http\Middleware\ConfigComplete;
use App\Http\Request\ConfigurationPostRequest;
use App\Services\CSV\Configuration\Configuration;
use App\Services\CSV\Converter\Date;
use App\Services\CSV\Specifics\SpecificService;
use App\Services\Session\Constants;
use App\Services\Storage\StorageService;
use App\Support\Token;
use Carbon\Carbon;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JsonException;
use Log;

/**
 * Class ConfigurationController
 */
class ConfigurationController extends Controller
{
    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Import configuration');
        $this->middleware(ConfigComplete::class);
    }

    /**
     * @return Factory|RedirectResponse|View
     */
    public function index(Request $request)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $mainTitle = 'Import routine';
        $subTitle  = 'Configure your CSV file import';
        $accounts  = [];

        $configuration = null;
        if (session()->has(Constants::CONFIGURATION)) {
            $configuration = Configuration::fromArray(session()->get(Constants::CONFIGURATION));
        }
        // if config says to skip it, skip it:
        $overruleSkip = 'true' === $request->get('overruleskip');
        if (null !== $configuration && true === $configuration->isSkipForm() && false === $overruleSkip) {
            // skipForm
            return redirect()->route('import.roles.index');
        }

        // get list of asset accounts:
        $url     = Token::getURL();
        $token   = Token::getAccessToken();
        $request = new GetAccountsRequest($url, $token);
        $request->setType(GetAccountsRequest::ASSET);
        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));
        $response = $request->get();

        // get list of specifics:
        $specifics = SpecificService::getSpecifics();

        /** @var Account $account */
        foreach ($response as $account) {
            $accounts['Asset accounts'][$account->id] = $account;
        }

        // also get liabilities
        $url     = Token::getURL();
        $token   = Token::getAccessToken();
        $request = new GetAccountsRequest($url, $token);
        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));
        $request->setType(GetAccountsRequest::LIABILITIES);
        $response = $request->get();
        /** @var Account $account */
        foreach ($response as $account) {
            $accounts['Liabilities'][$account->id] = $account;
        }

        // created default configuration object for sensible defaults:
        if (null === $configuration) {
            $configuration = Configuration::make();
        }

        return view(
            'import.configuration.index',
            compact('mainTitle', 'subTitle', 'accounts', 'specifics', 'configuration')
        );
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function phpDate(Request $request): JsonResponse
    {
        Log::debug(sprintf('Method %s', __METHOD__));

        $dateObj = new Date;
        [$locale, $format] = $dateObj->splitLocaleFormat($request->get('format'));
        $date = Carbon::make('1984-09-17')->locale($locale);

        return response()->json(['result' => $date->translatedFormat($format)]);
    }

    /**
     * @param ConfigurationPostRequest $request
     *
     * @return RedirectResponse
     */
    public function postIndex(ConfigurationPostRequest $request): RedirectResponse
    {
        Log::debug(sprintf('Now running %s', __METHOD__));
        // store config on drive.
        $fromRequest   = $request->getAll();
        $configuration = Configuration::fromRequest($fromRequest);

        $json = '[]';
        try {
            $json = json_encode($configuration, JSON_THROW_ON_ERROR, 512);
        } catch (JsonException $e) {
            Log::error($e->getMessage());
        }
        StorageService::storeContent($json);

        session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());

        // set config as complete.
        session()->put(Constants::CONFIG_COMPLETE_INDICATOR, true);

        // redirect to import things?
        return redirect()->route('import.roles.index');
    }

}
