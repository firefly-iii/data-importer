<?php

declare(strict_types=1);
/*
 * AccountListCollector.php
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

namespace App\Services\Shared\Http;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\LunchFlow\Authentication\SecretManager as LunchFlowSecretManager;
use App\Services\LunchFlow\Request\GetAccountsRequest as LunchFlowGetAccountsRequest;
use App\Services\LunchFlow\Response\ErrorResponse;
use App\Services\Nordigen\Model\Account as NordigenAccount;
use App\Services\Nordigen\Request\ListAccountsRequest;
use App\Services\Nordigen\Response\ListAccountsResponse;
use App\Services\Nordigen\Services\AccountInformationCollector;
use App\Services\Nordigen\TokenManager;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Model\ImportServiceAccount;
use App\Services\Spectre\Authentication\SecretManager as SpectreSecretManager;
use App\Services\Spectre\Request\GetAccountsRequest as SpectreGetAccountsRequest;
use App\Services\Spectre\Response\GetAccountsResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AccountListCollector
{
    private array         $importServiceAccounts = [];
    private array         $mergedAccounts        = [];

    public function __construct(private readonly Configuration $configuration, private readonly string $flow, private array $existingAccounts) {}

    /**
     * @throws AgreementExpiredException|ImporterErrorException
     */
    public function collect(): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));

        // no choice but to split based on flow.

        switch ($this->flow) {
            default:
                throw new ImporterErrorException(sprintf('Cannot collect accounts for flow "%s"', $this->flow));

            case 'file':
                return [];

            case 'nordigen':
                $this->collectGoCardlessAccounts();

                break;

//            case 'simplefin':
//                $this->collectSimpleFINAccounts();
//
//                break;

            case 'spectre':
                $this->collectSpectreAccounts();

                break;

            case 'lunchflow':
                $this->collectLunchFlowAccounts();
        }

        // $this->collectImportServiceAccounts();

        $this->mergeAccounts();

        return $this->mergedAccounts;
    }

    private function collectGoCardlessAccounts(): void
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $requisitions                = $this->configuration->getNordigenRequisitions();
        $return                      = [];
        $cache                       = [];
        foreach ($requisitions as $requisition) {
            $inCache = Cache::has($requisition) && config('importer.use_cache');
            // if cached, return it.
            if ($inCache) {
                $result = Cache::get($requisition);
                foreach ($result as $arr) {
                    $return[] = NordigenAccount::fromLocalArray($arr);
                }
                Log::debug('Grab accounts from cache', $result);
            }
            if (!$inCache) {
                // get banks and countries
                $accessToken = TokenManager::getAccessToken();
                $url         = config('nordigen.url');
                $request     = new ListAccountsRequest($url, $requisition, $accessToken);
                $request->setTimeOut(config('importer.connection.timeout'));

                /** @var ListAccountsResponse $response */
                try {
                    $response = $request->get();
                } catch (ImporterErrorException|ImporterHttpException $e) {
                    throw new ImporterErrorException($e->getMessage(), 0, $e);
                }
                $total       = count($response);
                Log::debug(sprintf('Found %d GoCardless accounts.', $total));

                /** @var NordigenAccount $account */
                foreach ($response as $index => $account) {
                    Log::debug(sprintf('[%s] [%d/%d] Now collecting information for account %s', config('importer.version'), $index + 1, $total, $account->getIdentifier()), $account->toLocalArray());
                    $account  = AccountInformationCollector::collectInformation($account, true);
                    $return[] = $account;
                    $cache[]  = $account->toLocalArray();
                }
            }
            Cache::put($requisition, $cache, 1800); // half an hour
        }
        $this->importServiceAccounts = $return;
    }

    private function mergeAccounts(): void
    {
        Log::debug(sprintf('Now merging "%s" account lists.', $this->flow));

        $generic              = match ($this->flow) {
            'nordigen'  => ImportServiceAccount::convertNordigenArray($this->importServiceAccounts),
            'simplefin' => ImportServiceAccount::convertSimpleFINArray($this->importServiceAccounts),
            'lunchflow' => ImportServiceAccount::convertLunchflowArray($this->importServiceAccounts),
            default     => throw new ImporterErrorException(sprintf('Need to merge account lists, but cannot handle "%s"', $this->flow)),
        };
        $this->mergedAccounts = $this->mergeGenericAccountList($generic);
    }

    private function mergeGenericAccountList(array $list): array
    {
        $return = [];

        /** @var ImportServiceAccount $importServiceAccount */
        foreach ($list as $importServiceAccount) {
            Log::debug(sprintf('Working on generic account name: "%s": id:"%s" (iban:"%s", number:"%s")', $importServiceAccount->name, $importServiceAccount->id, $importServiceAccount->iban, $importServiceAccount->bban));

            $entry          = [
                'import_account'       => $importServiceAccount,
                'mapped_to'            => null,
                'firefly_iii_accounts' => [
                    Constants::ASSET_ACCOUNTS => [],
                    Constants::LIABILITIES    => [],
                ],
            ];

            // Always show all accounts, but sort matches to the top
            $filteredByData = $this->filterByAccountData($importServiceAccount->iban, $importServiceAccount->bban, $importServiceAccount->name);

            foreach ([Constants::ASSET_ACCOUNTS, Constants::LIABILITIES] as $key) {
                $matching                            = $filteredByData[$key];
                $all                                 = $this->existingAccounts[$key];

                // Remove matching from all to avoid duplicates
                $nonMatching                         = array_udiff($all, $matching, fn ($a, $b) => $a->id <=> $b->id);

                // Concatenate: matches first, then the rest
                $entry['firefly_iii_accounts'][$key] = array_merge($matching, $nonMatching);
                if (count($matching) > 0 && array_key_exists(0, $matching)) {
                    Log::debug(sprintf('Set matching account ID to "%s"', $matching[0]->id));
                    $entry['mapped_to'] = (string)$matching[0]->id;
                }
            }

            $return[]       = $entry;
        }
        Log::debug(sprintf('Merged into %d accounts.', count($return)));

        return $return;
    }

    protected function filterByAccountData(string $iban, string $number, string $name): array
    {
        Log::debug(sprintf('Now filtering Firefly III accounts by IBAN "%s" or number "%s" or name "%s".', $iban, $number, $name));
        $result = [
            Constants::ASSET_ACCOUNTS => [],
            Constants::LIABILITIES    => [],
        ];
        foreach ($this->existingAccounts as $key => $accounts) {
            foreach ($accounts as $account) {
                if ($name === $account->name || $iban === $account->iban || $number === $account->number || $iban === $account->number || $number === $account->iban) {
                    Log::debug(sprintf('Found existing Firefly III account #%d.', $account->id));
                    $result[$key][] = $account;
                }
            }
        }

        return $result;
    }

    private function collectSpectreAccounts(): void
    {
        $return                      = [];
        $url                         = config('spectre.url');
        $appId                       = SpectreSecretManager::getAppId();
        $secret                      = SpectreSecretManager::getSecret();
        $spectreList                 = new SpectreGetAccountsRequest($url, $appId, $secret);
        $spectreList->setTimeOut(config('importer.connection.timeout'));
        $spectreList->connection     = $this->configuration->getConnection();

        /** @var GetAccountsResponse $spectreAccounts */
        $spectreAccounts             = $spectreList->get();
        foreach ($spectreAccounts as $account) {
            $return[] = $account;
        }

        $this->importServiceAccounts = $return;

    }

    private function collectSimpleFINAccounts(): void
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        $accountsData                = session()->get(Constants::SIMPLEFIN_ACCOUNTS_DATA, []);
        $accounts                    = [];

        foreach ($accountsData ?? [] as $account) {
            // Ensure the account has required SimpleFIN protocol fields
            if (!array_key_exists('id', $account) || '' === (string)$account['id']) {
                Log::warning('SimpleFIN account data is missing a valid ID, skipping.', ['account_data' => $account]);

                continue;
            }

            if (!array_key_exists('name', $account) || null === $account['name']) {
                Log::warning('SimpleFIN account data is missing name field, adding default.', ['account_id' => $account['id']]);
                $account['name'] = sprintf('Unknown Account (ID: %s)', $account['id']);
            }

            if (!array_key_exists('currency', $account) || null === $account['currency']) {
                Log::warning('SimpleFIN account data is missing currency field, this may cause issues.', ['account_id' => $account['id']]);
            }

            if (!array_key_exists('balance', $account) || null === $account['balance']) {
                Log::warning('SimpleFIN account data is missing balance field, this may cause issues.', ['account_id' => $account['id']]);
            }

            // Preserve raw SimpleFIN protocol data structure
            $accounts[] = $account;
        }
        Log::debug(sprintf('Collected %d SimpleFIN accounts from session.', count($accounts)));
        $this->importServiceAccounts = $accounts;
    }

    private function collectLunchFlowAccounts(): void
    {
        $return                      = [];
        $url                         = config('lunchflow.api_url');
        $apiKey                      = LunchFlowSecretManager::getApiKey($this->configuration);
        $req                         = new LunchFlowGetAccountsRequest($apiKey);
        $req->setTimeOut(config('importer.connection.timeout'));

        /** @var ErrorResponse|GetAccountsResponse $accounts */
        $accounts                    = $req->get();

        if ($accounts instanceof ErrorResponse) {
            $message = config(sprintf('importer.http_codes.%d', $accounts->statusCode));

            throw new ImporterErrorException(sprintf('LunchFlow API error with HTTP code %d: %s', $accounts->statusCode, $message));
        }

        foreach ($accounts as $account) {
            $return[] = $account;
        }

        $this->importServiceAccounts = $return;
    }
}
