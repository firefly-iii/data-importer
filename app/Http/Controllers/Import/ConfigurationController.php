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
use App\Services\Spectre\Response\GetAccountsResponse as SpectreGetAccountsResponse;
use App\Services\Storage\StorageService;
use App\Support\Http\RestoresConfiguration;
use Cache;
use Carbon\Carbon;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JsonException;
use League\Flysystem\Config;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

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
     * @throws ApiHttpException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function index(Request $request)
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $mainTitle    = 'Configuration';
        $overruleSkip = 'true' === $request->get('overruleskip');
        $subTitle     = 'Configure your import(s)';
        $ff3Accounts  = $this->getFF3Accounts();
        $combinations = session()->get(Constants::UPLOADED_COMBINATIONS);
        if (!is_array($combinations)) {
            die('Must be array');
        }
        if (count($combinations) < 1) {
            die('Must be more than zero.');
        }

        $data = [];
        /** @var array $entry */
        foreach ($combinations as $entry) {
            $data[] = $this->processCombination($entry, $ff3Accounts, $overruleSkip);
        }

        return view('import.004-configure.index', compact('mainTitle', 'subTitle', 'ff3Accounts', 'data'));
    }

    /**
     * Each configuration/importable combination must be processed further.
     * // TODO move to separate processor
     * @param array $entry
     * @param array $ff3Accounts
     * @param bool  $overruleSkip
     * @return array
     * @throws ImporterErrorException
     * @throws ImporterHttpException
     */
    private function processCombination(array $entry, array $ff3Accounts, bool $overruleSkip): array
    {
        $return                      = $entry;
        $configuration               = $this->restoreConfigurationFromFile($entry['config_location']);
        $flow                        = $configuration->getFlow();
        $return['configuration']     = $configuration;
        $return['flow']              = $flow;
        $return['skip_form']         = true === $configuration->isSkipForm() && false === $overruleSkip;
        $return['importer_accounts'] = [];
        $return['firefly_iii_accounts'] = $ff3Accounts;
        $return['unique_columns']    = config('csv.unique_column_options');

        if ('nordigen' === $flow) {
            $return['unique_columns']    = config('nordigen.unique_column_options');
            $requisitions                = $configuration->getNordigenRequisitions();
            $reference                   = array_shift($requisitions);
            // TODO move to separate processor
            $return['importer_accounts'] = $this->mergeNordigenAccountLists($this->getNordigenAccounts($reference), $ff3Accounts);
        }

        if ('spectre' === $flow) {
            $return['unique_columns']    = config('spectre.unique_column_options');
            $url                         = config('spectre.url');
            $appId                       = SpectreSecretManager::getAppId();
            $secret                      = SpectreSecretManager::getSecret();
            $spectreList                 = new SpectreGetAccountsRequest($url, $appId, $secret);
            $spectreList->connection     = $configuration->getConnection();
            // TODO move to separate processor
            $return['importer_accounts'] = $this->mergeSpectreAccountLists($spectreList->get(), $ff3Accounts);
        }

        return $return;


        // if config says to skip it, skip it:
        //  must check for ALL.
//        if (null !== $configuration && true === $configuration->isSkipForm() && false === $overruleSkip) {
//            app('log')->debug('Skip configuration(s), go straight to the next step.');
//            // set config as complete.
//            session()->put(Constants::CONFIG_COMPLETE_INDICATOR, true);
//            if ('nordigen' === $configuration->getFlow() || 'spectre' === $configuration->getFlow()) {
//                // at this point, nordigen is ready for data conversion.
//                session()->put(Constants::READY_FOR_CONVERSION, true);
//            }
//            // skipForm
//            return redirect()->route('005-roles.index');
//        }

        //         // also get the nordigen / spectre accounts
        //        $importerAccounts = [];
        //        if ('nordigen' === $flow) {
        //            $uniqueColumns = config('nordigen.unique_column_options');
        //            $requisitions  = $configuration->getNordigenRequisitions();
        //            $reference     = array_shift($requisitions);
        //            // list all accounts in Nordigen:
        //            //$reference        = $configuration->getRequisition(session()->get(Constants::REQUISITION_REFERENCE));
        //            $importerAccounts = $this->getNordigenAccounts($reference);
        //            $importerAccounts = $this->mergeNordigenAccountLists($importerAccounts, $accounts);
        //        }
        //
        //        if ('spectre' === $flow) {
        //            $uniqueColumns           = config('spectre.unique_column_options');
        //            $url                     = config('spectre.url');
        //            $appId                   = SpectreSecretManager::getAppId();
        //            $secret                  = SpectreSecretManager::getSecret();
        //            $spectreList             = new SpectreGetAccountsRequest($url, $appId, $secret);
        //            $spectreList->connection = $configuration->getConnection();
        //            /** @var GetAccountsResponse $spectreAccounts */
        //            $spectreAccounts  = $spectreList->get();
        //            $importerAccounts = $this->mergeSpectreAccountLists($spectreAccounts, $accounts);
        //        }
    }

    /**
     * TODO move to helper.
     *
     * List Nordigen accounts with account details, balances, and 2 transactions (if present)
     * @param string $identifier
     * @return array
     * @throws ImporterErrorException
     */
    private function getNordigenAccounts(string $identifier): array
    {
        if (Cache::has($identifier) && config('importer.use_cache')) {
            $result = Cache::get($identifier);
            $return = [];
            foreach ($result as $arr) {
                $return[] = NordigenAccount::fromLocalArray($arr);
            }
            app('log')->debug('Grab accounts from cache', $result);
            return $return;
        }
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        // get banks and countries
        $accessToken = TokenManager::getAccessToken();
        $url         = config('nordigen.url');
        $request     = new ListAccountsRequest($url, $identifier, $accessToken);
        $request->setTimeOut(config('importer.connection.timeout'));
        /** @var ListAccountsResponse $response */
        try {
            $response = $request->get();
        } catch (ImporterErrorException|ImporterHttpException $e) {
            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }
        $total  = count($response);
        $return = [];
        $cache  = [];
        app('log')->debug(sprintf('Found %d accounts.', $total));

        /** @var Account $account */
        foreach ($response as $index => $account) {
            app('log')->debug(sprintf('[%d/%d] Now collecting information for account %s', ($index + 1), $total, $account->getIdentifier()), $account->toLocalArray());
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
        app('log')->debug('Now creating Nordigen account lists.');
        $return = [];
        /** @var NordigenAccount $nordigenAccount */
        foreach ($nordigen as $nordigenAccount) {
            app('log')->debug(sprintf('Now working on account "%s": "%s"', $nordigenAccount->getName(), $nordigenAccount->getIdentifier()));
            $iban     = $nordigenAccount->getIban();
            $currency = $nordigenAccount->getCurrency();
            $entry    = [
                'import_service' => $nordigenAccount,
                'firefly'        => [],
            ];

            // only iban?
            $filteredByIban = $this->filterByIban($firefly, $iban);

            if (1 === count($filteredByIban)) {
                app('log')->debug(sprintf('This account (%s) has a single Firefly III counter part (#%d, "%s", same IBAN), so will use that one.', $iban, $filteredByIban[0]->id, $filteredByIban[0]->name));
                $entry['firefly'] = $filteredByIban;
                $return[]         = $entry;
                continue;
            }
            app('log')->debug(sprintf('Found %d accounts with the same IBAN ("%s")', count($filteredByIban), $iban));

            // only currency?
            $filteredByCurrency = $this->filterByCurrency($firefly, $currency);

            if (count($filteredByCurrency) > 0) {
                app('log')->debug(sprintf('This account (%s) has some Firefly III counter parts with the same currency so will only use those.', $currency));
                $entry['firefly'] = $filteredByCurrency;
                $return[]         = $entry;
                continue;
            }
            app('log')->debug('No special filtering on the Firefly III account list.');
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
     * TODO move to some helper.
     *
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
     * TODO move to some helper.
     *
     * @param SpectreGetAccountsResponse $spectre
     * @param array                      $firefly
     *
     * TODO should be a helper
     * @return array
     */
    private function mergeSpectreAccountLists(SpectreGetAccountsResponse $spectre, array $firefly): array
    {
        $return = [];
        app('log')->debug('Now creating Spectre account lists.');

        foreach ($spectre as $spectreAccount) {
            app('log')->debug(sprintf('Now working on Spectre account "%s": "%s"', $spectreAccount->name, $spectreAccount->id));
            $iban     = $spectreAccount->iban;
            $currency = $spectreAccount->currencyCode;
            $entry    = [
                'import_service' => $spectreAccount,
                'firefly'        => [],
            ];

            // only iban?
            $filteredByIban = $this->filterByIban($firefly, $iban);

            if (1 === count($filteredByIban)) {
                app('log')->debug(sprintf('This account (%s) has a single Firefly III counter part (#%d, "%s", same IBAN), so will use that one.', $iban, $filteredByIban[0]->id, $filteredByIban[0]->name));
                $entry['firefly'] = $filteredByIban;
                $return[]         = $entry;
                continue;
            }
            app('log')->debug(sprintf('Found %d accounts with the same IBAN ("%s")', count($filteredByIban), $iban));

            // only currency?
            $filteredByCurrency = $this->filterByCurrency($firefly, $currency);

            if (count($filteredByCurrency) > 0) {
                app('log')->debug(sprintf('This account (%s) has some Firefly III counter parts with the same currency so will only use those.', $currency));
                $entry['firefly'] = $filteredByCurrency;
                $return[]         = $entry;
                continue;
            }
            app('log')->debug('No special filtering on the Firefly III account list.');
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
        app('log')->debug(sprintf('Method %s', __METHOD__));

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
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $data = $request->getAll();

        // loop each entry.
        if($data['count'] !== count($data['configurations'])) {
            throw new ImporterErrorException('Unexpected miscount in configuration array.');
        }
        $combinations = session()->get(Constants::UPLOADED_COMBINATIONS);
        if (!is_array($combinations)) {
            throw new ImporterErrorException('Combinations must be an array.');
        }
        if (count($combinations) < 1) {
            throw new ImporterErrorException('Combinations must be more than zero.');
        }

        if (count($combinations) !== $data['count']) {
            throw new ImporterErrorException('Combinations must be count-validated.');
        }
        var_dump($combinations);
        $configurations = [];
        foreach($data['configurations'] as $index => $current) {
            $object = Configuration::fromRequest($current);

            // TODO are all fields actually in the config?

            // loop accounts:
            $accounts = [];
            foreach (array_keys($current['do_import']) as $identifier) {
                if (isset($current['accounts'][$identifier])) {
                    $accounts[$identifier] = (int) $current['accounts'][$identifier];
                }
            }
            $object->setAccounts($accounts);
            $object->updateDateRange();

            $json = '{}';
            try {
                $json = json_encode($object->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            } catch (JsonException $e) {
                app('log')->error($e->getMessage());
                throw new ImporterErrorException($e->getMessage(), 0, $e);
            }
            ;
            $combinations[$index]['config_location'] = StorageService::storeContent($json);
            app('log')->debug(sprintf('Configuration debug: Connection ID is "%s"', $object->getConnection()));

            // set config as complete.
            session()->put(Constants::CONFIG_COMPLETE_INDICATOR, true);

            if ('nordigen' === $object->getFlow() || 'spectre' === $object->getFlow()) {
                // at this point, nordigen is ready for data conversion.
                session()->put(Constants::READY_FOR_CONVERSION, true);
            }
        }

        session()->put(Constants::UPLOADED_COMBINATIONS, $combinations);
        // always redirect to roles, even if this isn't the step yet
        // for nordigen and spectre, roles will be skipped right away.
        return redirect(route('005-roles.index'));


    }

    /**
     * TODO move to helper
     * @return array
     */
    private function getFF3Accounts(): array
    {
        $accounts = [
            self::ASSET_ACCOUNTS => [],
            self::LIABILITIES    => [],
        ];

        // get list of asset accounts:
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
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
        return $accounts;
    }


}
