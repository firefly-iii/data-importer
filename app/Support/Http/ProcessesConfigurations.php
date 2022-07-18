<?php
declare(strict_types=1);
/*
 * ProcessesConfigurations.php
 * Copyright (c) 2022 james@firefly-iii.org
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

namespace App\Support\Http;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Nordigen\Model\Account as NordigenAccount;
use App\Services\Nordigen\Request\ListAccountsRequest;
use App\Services\Nordigen\Response\ListAccountsResponse;
use App\Services\Nordigen\Services\AccountInformationCollector;
use App\Services\Nordigen\TokenManager;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Spectre\Authentication\SecretManager as SpectreSecretManager;
use App\Services\Spectre\Request\GetAccountsRequest as SpectreGetAccountsRequest;
use App\Services\Spectre\Response\GetAccountsResponse as SpectreGetAccountsResponse;
use App\Services\Storage\StorageService;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use Illuminate\Support\Facades\Cache;
use JsonException;

/**
 * Trait ProcessesConfigurations
 */
trait ProcessesConfigurations
{
    /**
     * POST processing of a configuration file:
     *
     * @param array       $set
     * @param string|null $configLocation
     * @return string
     * @throws ImporterErrorException
     */
    protected function postProcessConfiguration(array $set, ?string $configLocation = null): string
    {
        $object = Configuration::fromRequest($set);

        // loop accounts:
        $accounts = [];
        foreach (array_keys($set['do_import']) as $identifier) {
            if (isset($current['accounts'][$identifier])) {
                $accounts[$identifier] = (int) $current['accounts'][$identifier];
            }
        }
        $object->setAccounts($accounts);
        $object->updateDateRange();

        /*
         * If the user uploaded a file, the POST request did not include the entire configuration.
         * This was "left" in the uploaded file and must be restored into the new configuration object.
         * Assuming of course, the user even uploaded anything.
         */
        if (null !== $configLocation) {
            app('log')->debug(sprintf('Get configuration from old file "%s"', $configLocation));
            $previous       = json_decode(StorageService::getContent($configLocation), true);
            $previousConfig = Configuration::fromFile($previous);
            $object->setRoles($previousConfig->getRoles());
            $object->setDoMapping($previousConfig->getDoMapping());
            $object->setMapping($previousConfig->getMapping());
        }

        /*
         * Return the new config storage location:
         */
        try {
            $json = json_encode($object->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            app('log')->error($e->getMessage());
            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }

        return StorageService::storeContent($json);
    }

    /**
     * PRE-configuration processing.
     */
    protected function preProcessConfiguration(array $entry, array $ff3Accounts, bool $overruleSkip): array
    {
        $return                         = $entry;
        $configuration                  = $this->restoreConfigurationFromFile($entry['config_location']);
        $flow                           = $configuration->getFlow();
        $return['configuration']        = $configuration;
        $return['flow']                 = $flow;
        $return['type']                 = $entry['type'];
        $return['skip_form']            = true === $configuration->isSkipForm() && false === $overruleSkip;
        $return['importer_accounts']    = [];
        $return['firefly_iii_accounts'] = $ff3Accounts;
        $return['unique_columns']       = config('csv.unique_column_options');

        if ('nordigen' === $flow) {
            $return['unique_columns']    = config('nordigen.unique_column_options');
            $requisitions                = $configuration->getNordigenRequisitions();
            $reference                   = array_shift($requisitions);
            $return['importer_accounts'] = $this->mergeNordigenAccountLists($this->getNordigenAccounts($reference), $ff3Accounts);
        }

        if ('spectre' === $flow) {
            $return['unique_columns']    = config('spectre.unique_column_options');
            $url                         = config('spectre.url');
            $appId                       = SpectreSecretManager::getAppId();
            $secret                      = SpectreSecretManager::getSecret();
            $spectreList                 = new SpectreGetAccountsRequest($url, $appId, $secret);
            $spectreList->connection     = $configuration->getConnection();
            $return['importer_accounts'] = $this->mergeSpectreAccountLists($spectreList->get(), $ff3Accounts);
        }
        app('log')->debug('Configuration is (session array):', $configuration->toSessionArray());

        return $return;
    }

    /**
     * @param array $nordigen
     * @param array $firefly
     * @return array
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
            $entry    = ['import_service' => $nordigenAccount, 'firefly' => [],];

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
            $entry['firefly'] = array_merge($firefly[Constants::ASSET_ACCOUNTS], $firefly[Constants::LIABILITIES]);
            $return[]         = $entry;
        }
        return $return;
    }

    /**
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
        $all    = array_merge($firefly[Constants::ASSET_ACCOUNTS] ?? [], $firefly[Constants::LIABILITIES] ?? []);
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
        $all    = array_merge($firefly[Constants::ASSET_ACCOUNTS] ?? [], $firefly[Constants::LIABILITIES] ?? []);
        /** @var Account $account */
        foreach ($all as $account) {
            if ($currency === $account->currencyCode) {
                $result[] = $account;
            }
        }
        return $result;
    }

    /**
     * List Nordigen accounts with account details, balances, and 2 transactions (if present)
     *
     * @param string $identifier
     * @return array
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
     * @param SpectreGetAccountsResponse $spectre
     * @param array                      $firefly
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
            $entry    = ['import_service' => $spectreAccount, 'firefly' => [],];

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

}
