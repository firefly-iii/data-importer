<?php

namespace App\Services\Shared\Http;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Nordigen\Model\Account as NordigenAccount;
use App\Services\Nordigen\Request\ListAccountsRequest;
use App\Services\Nordigen\Response\ListAccountsResponse;
use App\Services\Nordigen\Services\AccountInformationCollector;
use App\Services\Nordigen\TokenManager;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Model\ImportServiceAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AccountListCollector
{
    private string        $flow;
    private Configuration $configuration;
    private array         $existingAccounts;
    private array         $importServiceAccounts = [];
    private array         $mergedAccounts        = [];

    public function __construct(Configuration $configuration, string $flow, array $existingAccounts)
    {
        $this->configuration    = $configuration;
        $this->flow             = $flow;
        $this->existingAccounts = $existingAccounts;
    }

    /**
     * @return array
     * @throws ImporterErrorException|AgreementExpiredException
     */
    public function collect(): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        // file flow needs no collection:
        if ('file' === $this->flow) {
            return [];
        }

        // nordigen has its own flow.
        if ('nordigen' === $this->flow) {
            $this->collectGoCardlessAccounts();
        }

        // same for SimpleFIN (accounts are collected during authentication).
        if ('simplefin' === $this->flow) {
            $this->collectSimpleFINAccounts();
        }


        // the rest is unified, splits at a later point.
        if ('nordigen' !== $this->flow) {
            $this->collectImportServiceAccounts();
        }

        $this->mergeAccounts();
        return $this->mergedAccounts;

        $this->flow;
        $this->configuration;
        $this->existingAccounts;
        return [];
    }

    private function collectGoCardlessAccounts(): void
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $requisitions = $this->configuration->getNordigenRequisitions();
        $return       = [];
        $cache        = [];
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
                $total = count($response);
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
        switch ($this->flow) {
            case 'nordigen':
                $generic = ImportServiceAccount::convertNordigenArray($this->importServiceAccounts);
                break;
            case 'simplefin':
                $generic = ImportServiceAccount::convertSimpleFINArray($this->importServiceAccounts);
                break;
            default:
                throw new ImporterErrorException(sprintf('Need to merge account lists, but cannot handle "%s"', $this->flow));
        }
        $this->mergedAccounts = $this->mergeGenericAccountList($generic);
    }

    private function mergeGenericAccountList(array $list): array
    {
        $return = [];

        /** @var ImportServiceAccount $importServiceAccount */
        foreach ($list as $importServiceAccount) {
            Log::debug(sprintf('Working on generic account name: "%s": id:"%s" (iban:"%s", number:"%s")', $importServiceAccount->name, $importServiceAccount->id, $importServiceAccount->iban, $importServiceAccount->bban));

            $entry = [
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
                $matching = $filteredByData[$key];
                $all      = $this->existingAccounts[$key];

                // Remove matching from all to avoid duplicates
                $nonMatching = array_udiff($all, $matching, function ($a, $b) {
                    return $a->id <=> $b->id;
                });

                // Concatenate: matches first, then the rest
                $entry['firefly_iii_accounts'][$key] = array_merge($matching, $nonMatching);
                if (count($matching) > 0 && array_key_exists(0, $matching)) {
                    Log::debug(sprintf('Set matching account ID to "%s"', $matching[0]->id));
                    $entry['mapped_to'] = (string)$matching[0]->id;
                }
            }

            $return[] = $entry;
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

    private function collectImportServiceAccounts(): void
    {

    }

    private function collectSimpleFINAccounts(): void
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        $accountsData = session()->get(Constants::SIMPLEFIN_ACCOUNTS_DATA, []);
        $accounts     = [];

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

}
