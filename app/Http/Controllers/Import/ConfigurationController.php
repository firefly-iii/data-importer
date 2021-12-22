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
use App\Services\Nordigen\Model\Account as NordigenAccount;
use App\Services\Nordigen\Request\ListAccountsRequest;
use App\Services\Nordigen\Response\ListAccountsResponse;
use App\Services\Nordigen\Services\AccountInformationCollector;
use App\Services\Nordigen\TokenManager;
use App\Services\Session\Constants;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Spectre\Authentication\SecretManager as SpectreSecretManager;
use App\Services\Spectre\Request\GetAccountsRequest as SpectreGetAccountsRequest;
use App\Services\Spectre\Response\GetAccountsResponse;
use App\Services\Spectre\Response\GetAccountsResponse as SpectreGetAccountsResponse;
use App\Services\Storage\StorageService;
use App\Support\Http\RestoresConfiguration;
use Cache;
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
 * TODO for spectre and nordigen duplicate detection is only on transaction id
 */
class ConfigurationController extends Controller
{
    protected const ASSET_ACCOUNTS = 'assets';
    protected const LIABILITIES    = 'liabilities';

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
     * @param Request $request
     * @return Factory|RedirectResponse|View
     * @throws ImporterErrorException
     * @throws ImporterHttpException
     * @throws \GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function index(Request $request)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $mainTitle = 'Configuration';
        $subTitle  = 'Configure your import';
        $accounts  = [
            self::ASSET_ACCOUNTS => [],
            self::LIABILITIES    => [],
        ];
        $flow      = $request->cookie(Constants::FLOW_COOKIE); // TODO should be from configuration right

        // create configuration:
        $configuration = $this->restoreConfiguration();

        // if config says to skip it, skip it:
        $overruleSkip = 'true' === $request->get('overruleskip');
        if (null !== $configuration && true === $configuration->isSkipForm() && false === $overruleSkip) {
            // skipForm
            return redirect()->route('005-roles.index');
        }

        // get list of asset accounts:
        $url   = SecretManager::getBaseUrl();
        $token = SecretManager::getAccessToken();


        $request = new GetAccountsRequest($url, $token);
        $request->setType(GetAccountsRequest::ASSET);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $response = $request->get();

        /** @var Account $account */
        foreach ($response as $account) {
            $accounts[self::ASSET_ACCOUNTS][$account->id] = $account;
        }

        // also get liabilities
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
        $request = new GetAccountsRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setType(GetAccountsRequest::LIABILITIES);
        $response = $request->get();
        /** @var Account $account */
        foreach ($response as $account) {
            $accounts[self::LIABILITIES][$account->id] = $account;
        }

        // possibilities for duplicate detection (unique columns)
        $uniqueColumns = config('csv.unique_column_options');

        // also get the nordigen / spectre accounts
        $importerAccounts = [];
        if ('nordigen' === $flow) {
            $uniqueColumns = config('nordigen.unique_column_options');
            $requisitions  = $configuration->getNordigenRequisitions();
            $reference     = array_shift($requisitions);
            // list all accounts in Nordigen:
            //$reference        = $configuration->getRequisition(session()->get(Constants::REQUISITION_REFERENCE));
            $importerAccounts = $this->getNordigenAccounts($reference);
            $importerAccounts = $this->mergeNordigenAccountLists($importerAccounts, $accounts);
        }

        if ('spectre' === $flow) {
            $uniqueColumns           = config('spectre.unique_column_options');
            $url                     = config('spectre.url');
            $appId                   = SpectreSecretManager::getAppId();
            $secret                  = SpectreSecretManager::getSecret();
            $spectreList             = new SpectreGetAccountsRequest($url, $appId, $secret);
            $spectreList->connection = $configuration->getConnection();
            /** @var GetAccountsResponse $spectreAccounts */
            $spectreAccounts  = $spectreList->get();
            $importerAccounts = $this->mergeSpectreAccountLists($spectreAccounts, $accounts);
        }

        return view(
            'import.004-configure.index',
            compact('mainTitle', 'subTitle', 'accounts', 'configuration', 'flow', 'importerAccounts', 'uniqueColumns')
        );
    }

    /**
     * List Nordigen accounts with account details, balances, and 2 transactions (if present)
     * @param string $identifier
     * @return array
     * @throws ImporterErrorException
     */
    private function getNordigenAccounts(string $identifier): array
    {
        if (Cache::has($identifier)) {
            $result = Cache::get($identifier);
            $return = [];
            foreach ($result as $arr) {
                $return[] = NordigenAccount::fromLocalArray($arr);
            }
            Log::debug('Grab accounts from cache', $result);
            return $return;
        }
        Log::debug(sprintf('Now in %s', __METHOD__));
        // get banks and countries
        $accessToken = TokenManager::getAccessToken();
        $url         = config('nordigen.url');
        $request     = new ListAccountsRequest($url, $identifier, $accessToken);
        /** @var ListAccountsResponse $response */
        try {
            $response = $request->get();
        } catch (ImporterErrorException $e) {
        } catch (ImporterHttpException $e) {
            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }
        $total  = count($response);
        $return = [];
        $cache  = [];
        Log::debug(sprintf('Found %d accounts.', $total));

        /** @var Account $account */
        foreach ($response as $index => $account) {
            Log::debug(sprintf('[%d/%d] Now collecting information for account %s', ($index + 1), $total, $account->getIdentifier()), $account->toLocalArray());
            $account  = AccountInformationCollector::collectInformation($account);
            $return[] = $account;
            $cache[]  = $account->toLocalArray();
        }
        Cache::put($identifier, $cache, 1800); // half an hour
        return $return;
    }

    /**
     * @param array $nordigen
     * @param array $firefly
     * @return array
     *
     * TODO move to some helper.
     */
    private function mergeNordigenAccountLists(array $nordigen, array $firefly): array
    {
        Log::debug('Now creating Nordigen account lists.');
        $return = [];
        /** @var NordigenAccount $nordigenAccount */
        foreach ($nordigen as $nordigenAccount) {
            Log::debug(sprintf('Now working on account "%s": "%s"', $nordigenAccount->getName(), $nordigenAccount->getIdentifier()));
            $iban     = $nordigenAccount->getIban();
            $currency = $nordigenAccount->getCurrency();
            $entry    = [
                'import_service' => $nordigenAccount,
                'firefly'        => [],
            ];

            // only iban?
            $filteredByIban = $this->filterByIban($firefly, $iban);

            if (1 === count($filteredByIban)) {
                Log::debug(sprintf('This account (%s) has a single Firefly III counter part (#%d, "%s", same IBAN), so will use that one.', $iban, $filteredByIban[0]->id, $filteredByIban[0]->name));
                $entry['firefly'] = $filteredByIban;
                $return[]         = $entry;
                continue;
            }
            Log::debug(sprintf('Found %d accounts with the same IBAN ("%s")', count($filteredByIban), $iban));

            // only currency?
            $filteredByCurrency = $this->filterByCurrency($firefly, $currency);

            if (count($filteredByCurrency) > 0) {
                Log::debug(sprintf('This account (%s) has some Firefly III counter parts with the same currency so will only use those.', $currency));
                $entry['firefly'] = $filteredByCurrency;
                $return[]         = $entry;
                continue;
            }
            Log::debug('No special filtering on the Firefly III account list.');
            $entry['firefly'] = array_merge($firefly[self::ASSET_ACCOUNTS], $firefly[self::LIABILITIES]);
            $return[]         = $entry;
        }
        return $return;
    }

    /**
     * TODO move to some helper.
     *
     * @param array  $firefly
     * @param string $iban
     * @return array
     */
    private function filterByIban(array $firefly, string $iban): array
    {
        if ('' === $iban) {
            return [];
        }
        $result = [];
        $all    = array_merge($firefly[self::ASSET_ACCOUNTS] ?? [], $firefly[self::LIABILITIES] ?? []);
        /** @var Account $account */
        foreach ($all as $account) {
            if ($iban === $account->iban) {
                $result[] = $account;
            }
        }
        return $result;
    }

    /**
     * @param array  $firefly
     * @param string $currency
     * @return array
     */
    private function filterByCurrency(array $firefly, string $currency): array
    {
        if ('' === $currency) {
            return [];
        }
        $result = [];
        $all    = array_merge($firefly[self::ASSET_ACCOUNTS] ?? [], $firefly[self::LIABILITIES] ?? []);
        /** @var Account $account */
        foreach ($all as $account) {
            if ($currency === $account->currencyCode) {
                $result[] = $account;
            }
        }
        return $result;
    }

    /**
     * @param SpectreGetAccountsResponse $spectre
     * @param array                      $firefly
     *
     * TODO should be a helper
     * @return array
     */
    private function mergeSpectreAccountLists(SpectreGetAccountsResponse $spectre, array $firefly): array
    {
        $return = [];
        Log::debug('Now creating Spectre account lists.');

        foreach ($spectre as $spectreAccount) {
            Log::debug(sprintf('Now working on Spectre account "%s": "%s"', $spectreAccount->name, $spectreAccount->id));
            $iban     = $spectreAccount->iban;
            $currency = $spectreAccount->currencyCode;
            $entry    = [
                'import_service' => $spectreAccount,
                'firefly'        => [],
            ];

            // only iban?
            $filteredByIban = $this->filterByIban($firefly, $iban);

            if (1 === count($filteredByIban)) {
                Log::debug(sprintf('This account (%s) has a single Firefly III counter part (#%d, "%s", same IBAN), so will use that one.', $iban, $filteredByIban[0]->id, $filteredByIban[0]->name));
                $entry['firefly'] = $filteredByIban;
                $return[]         = $entry;
                continue;
            }
            Log::debug(sprintf('Found %d accounts with the same IBAN ("%s")', count($filteredByIban), $iban));

            // only currency?
            $filteredByCurrency = $this->filterByCurrency($firefly, $currency);

            if (count($filteredByCurrency) > 0) {
                Log::debug(sprintf('This account (%s) has some Firefly III counter parts with the same currency so will only use those.', $currency));
                $entry['firefly'] = $filteredByCurrency;
                $return[]         = $entry;
                continue;
            }
            Log::debug('No special filtering on the Firefly III account list.');
            $entry['firefly'] = $firefly;
            $return[]         = $entry;
        }
        return $return;
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
        [$locale, $format] = $dateObj->splitLocaleFormat((string) $request->get('format'));
        $date = Carbon::make('1984-09-17')->locale($locale);

        return response()->json(['result' => $date->translatedFormat($format)]);
    }

    /**
     * @param ConfigurationPostRequest $request
     *
     * @return RedirectResponse
     * @throws ImporterErrorException
     */
    public function postIndex(ConfigurationPostRequest $request): RedirectResponse
    {
        Log::debug(sprintf('Now running %s', __METHOD__));
        // store config on drive.
        $fromRequest   = $request->getAll();
        $configuration = Configuration::fromRequest($fromRequest);
        $configuration->setFlow($request->cookie(Constants::FLOW_COOKIE));

        // TODO are all fields actually in the config?

        // loop accounts:
        $accounts = [];
        foreach (array_keys($fromRequest['do_import']) as $identifier) {
            if (isset($fromRequest['accounts'][$identifier])) {
                $accounts[$identifier] = (int) $fromRequest['accounts'][$identifier];
            }
        }
        $configuration->setAccounts($accounts);
        $configuration->updateDateRange();


        $json = '{}';
        try {
            $json = json_encode($configuration->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            Log::error($e->getMessage());
            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }
        StorageService::storeContent($json);

        session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());


        Log::debug(sprintf('Configuration debug: Connection ID is "%s"', $configuration->getConnection()));
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
