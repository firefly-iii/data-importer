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

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ConfigurationControllerMiddleware;
use App\Http\Request\ConfigurationPostRequest;
use App\Services\CSV\Converter\Date;
use App\Services\CSV\Mapper\TransactionCurrencies;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\File\FileContentSherlock;
use App\Services\SimpleFIN\Validation\ConfigurationContractValidator;
use App\Services\Storage\StorageService;
use App\Support\Http\RestoresConfiguration;
use App\Support\Internal\CollectsAccounts;
use App\Support\Internal\MergesAccountLists;
use Carbon\Carbon;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Exception;
use JsonException;

/**
 * Class ConfigurationController
 * TODO for spectre and nordigen duplicate detection is only on transaction id
 */
class ConfigurationController extends Controller
{
    use CollectsAccounts;
    use MergesAccountLists;
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
     * @return Factory|RedirectResponse|View
     */
    public function index(Request $request)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $mainTitle          = 'Configuration';
        $subTitle           = 'Configure your import';
        $flow               = $request->cookie(Constants::FLOW_COOKIE); // TODO should be from configuration right
        $configuration      = $this->restoreConfiguration();

        // if config says to skip it, skip it:
        $overruleSkip       = 'true' === $request->get('overruleskip');
        if (true === $configuration->isSkipForm() && false === $overruleSkip) {
            Log::debug('Skip configuration, go straight to the next step.');
            // set config as complete.
            session()->put(Constants::CONFIG_COMPLETE_INDICATOR, true);
            if ('nordigen' === $configuration->getFlow() || 'spectre' === $configuration->getFlow()) {
                // at this point, nordigen is ready for data conversion.
                session()->put(Constants::READY_FOR_CONVERSION, true);
            }

            // skipForm
            return redirect()->route('005-roles.index');
        }

        // collect Firefly III accounts
        // this function returns an array with keys 'assets' and 'liabilities', each containing an array of Firefly III accounts.
        $fireflyIIIaccounts = $this->getFireflyIIIAccounts();
        // possibilities for duplicate detection (unique columns)

        // also get the nordigen / spectre accounts
        $importerAccounts   = [];
        $uniqueColumns      = config('csv.unique_column_options');
        if ('nordigen' === $flow) {
            // TODO here we need to redirect to Nordigen.
            try {
                $importerAccounts = $this->getNordigenAccounts($configuration);
            } catch (AgreementExpiredException $e) {
                Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

                // remove thing from configuration
                $configuration->clearRequisitions();

                // save configuration in session and on disk:
                session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
                $configFileName = StorageService::storeContent((string)json_encode($configuration->toArray(), JSON_PRETTY_PRINT));
                session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);

                // redirect to selection.
                return redirect()->route('009-selection.index');
            }
            $uniqueColumns    = config('nordigen.unique_column_options');
            $importerAccounts = $this->mergeNordigenAccountLists($importerAccounts, $fireflyIIIaccounts);
        }

        if ('spectre' === $flow) {
            $importerAccounts = $this->getSpectreAccounts($configuration);
            $uniqueColumns    = config('spectre.unique_column_options');
            $importerAccounts = $this->mergeSpectreAccountLists($importerAccounts, $fireflyIIIaccounts);
        }

        if ('simplefin' === $flow) {
            $importerAccounts = $this->getSimpleFINAccounts();
            $uniqueColumns    = config('simplefin.unique_column_options', ['id']);
            $importerAccounts = $this->mergeSimpleFINAccountLists($importerAccounts, $fireflyIIIaccounts);
        }

        if ('file' === $flow) {
            // detect content type and save to config object.
            $detector = new FileContentSherlock();
            $content  = StorageService::getContent(session()->get(Constants::UPLOAD_DATA_FILE), $configuration->isConversion());
            $fileType = $detector->detectContentTypeFromContent($content);
            $configuration->setContentType($fileType);
        }
        // Get currency data for account creation widget
        $currencies         = $this->getCurrencies();

        return view('import.004-configure.index', compact('mainTitle', 'subTitle', 'fireflyIIIaccounts', 'configuration', 'flow', 'importerAccounts', 'uniqueColumns', 'currencies'));
    }

    /**
     * Get SimpleFIN accounts from session data
     */
    private function getSimpleFINAccounts(): array
    {
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

        return $accounts;
    }

    /**
     * Merge SimpleFIN accounts with Firefly III accounts
     */
    private function mergeSimpleFINAccountLists(array $simplefinAccounts, array $fireflyAccounts): array
    {
        $return = [];

        foreach ($simplefinAccounts as $sfinAccountData) {
            // $sfinAccountData is raw SimpleFIN protocol data with fields:
            // ['id', 'name', 'currency', 'balance', 'balance-date', 'org', etc.]

            $importAccountRepresentation = (object)['id'              => $sfinAccountData['id'], // Expected by component for form elements, and by getMappedTo (as 'identifier')
                'name'                                                => $sfinAccountData['name'], // Expected by getMappedTo, display in component
                'status'                                              => 'active', // Expected by view for status checks
                'currency'                                            => $sfinAccountData['currency'] ?? null, // SimpleFIN currency field
                'balance'                                             => $sfinAccountData['balance'] ?? null, // SimpleFIN balance (numeric string)
                'balance_date'                                        => $sfinAccountData['balance-date'] ?? null, // SimpleFIN balance timestamp
                'org'                                                 => $sfinAccountData['org'] ?? null, // SimpleFIN organization data
                'iban'                                                => null, // Placeholder for consistency if component expects it
                'extra'                                               => $sfinAccountData['extra'] ?? [], // SimpleFIN extra data
                'bic'                                                 => null, // Placeholder
                'product'                                             => null, // Placeholder
                'cashAccountType'                                     => null, // Placeholder
                'usage'                                               => null, // Placeholder
                'resourceId'                                          => null, // Placeholder
                'bban'                                                => null, // Placeholder
                'ownerName'                                           => null, // Placeholder
            ];


            $return[]                    = ['import_account'       => $importAccountRepresentation, // The DTO-like object for the component
                'name'                                             => $sfinAccountData['name'], // SimpleFIN account name
                'id'                                               => $sfinAccountData['id'], // ID for form fields (do_import[ID], accounts[ID])
                'mapped_to'                                        => $this->getMappedTo((object)['identifier' => $importAccountRepresentation->id, 'name' => $importAccountRepresentation->name], $fireflyAccounts), // getMappedTo needs 'identifier'
                'type'                                             => 'source', // Indicates it's an account from the import source
                'firefly_iii_accounts'                             => $fireflyAccounts, // Required by x-importer-account component
            ];
        }


        return $return;
    }

    /**
     * Stub for determining if an imported account is mapped to a Firefly III account.
     * TODO: Implement actual mapping logic.
     * TODO get rid of object casting.
     *
     * @param object $importAccount   An object representing the account from the import source.
     *                                Expected to have at least 'identifier' and 'name' properties.
     * @param array  $fireflyAccounts array of existing Firefly III accounts
     *
     * @return ?string the ID of the mapped Firefly III account, or null if not mapped
     */
    private function getMappedTo(object $importAccount, array $fireflyAccounts): ?string
    {
        $importAccountName = $importAccount->name ?? null; // @phpstan-ignore-line

        if ('' === (string) $importAccountName || null === $importAccountName) { // same thing really.
            return null;
        }


        // Check assets accounts for name match
        if (array_key_exists('assets', $fireflyAccounts) && is_array($fireflyAccounts['assets'])) {
            foreach ($fireflyAccounts['assets'] as $fireflyAccount) {
                $fireflyAccountName = $fireflyAccount->name ?? null;
                if (null !== $fireflyAccountName && '' !== $fireflyAccountName && trim(strtolower((string) $fireflyAccountName)) === trim(strtolower($importAccountName))) {
                    return (string)$fireflyAccount->id;
                }
            }
        }

        // Check liability accounts for name match
        if (array_key_exists('liabilities', $fireflyAccounts) && is_array($fireflyAccounts['liabilities'])) {
            foreach ($fireflyAccounts['liabilities'] as $fireflyAccount) {
                $fireflyAccountName = $fireflyAccount->name ?? null;
                if (null !== $fireflyAccountName && '' !== $fireflyAccountName && trim(strtolower((string) $fireflyAccountName)) === trim(strtolower($importAccountName))) {
                    return (string)$fireflyAccount->id;
                }
            }
        }

        return null;
    }

    /**
     * Get available currencies from Firefly III for account creation
     */
    private function getCurrencies(): array
    {
        try {
            /** @var TransactionCurrencies $mapper */
            $mapper = app(TransactionCurrencies::class);

            return $mapper->getMap();
        } catch (Exception $e) {
            Log::error(sprintf('Failed to load currencies: %s', $e->getMessage()));

            return [];
        }
    }

    public function phpDate(Request $request): JsonResponse
    {
        Log::debug(sprintf('Method %s', __METHOD__));

        $dateObj           = new Date();
        [$locale, $format] = $dateObj->splitLocaleFormat((string)$request->get('format'));

        /** @var Carbon $date */
        $date              = today()->locale($locale);

        return response()->json(['result' => $date->translatedFormat($format)]);
    }

    /**
     * @throws ImporterErrorException
     */
    public function postIndex(ConfigurationPostRequest $request): RedirectResponse
    {
        Log::debug(sprintf('Now running %s', __METHOD__));
        // store config on drive.v
        $fromRequest         = $request->getAll();
        $configuration       = Configuration::fromRequest($fromRequest);
        $configuration->setFlow($request->cookie(Constants::FLOW_COOKIE));

        // TODO are all fields actually in the config?

        // loop accounts:
        $accounts            = [];
        $allNewAccounts      = $fromRequest['new_account'] ?? [];
        $toCreateNewAccounts = [];

        foreach (array_keys($fromRequest['do_import']) as $identifier) {
            if (array_key_exists($identifier, $fromRequest['accounts'])) {
                $accountValue          = (int)$fromRequest['accounts'][$identifier];
                $accounts[$identifier] = $accountValue;
            }
            if (array_key_exists($identifier, $allNewAccounts)) {
                // this is a new account to create.
                $toCreateNewAccounts[$identifier] = $allNewAccounts[$identifier];
            }
            if (!array_key_exists($identifier, $fromRequest['accounts'])) {
                Log::warning(sprintf('Account identifier %s in do_import but not in accounts array', $identifier));
            }
        }

        $configuration->setAccounts($accounts);
        $configuration->setNewAccounts($toCreateNewAccounts);

        // Store do_import selections in session for validation
        session()->put('do_import', $fromRequest['do_import'] ?? []);

        // Validate configuration contract for SimpleFIN
        if ('simplefin' === $configuration->getFlow()) {
            $validator          = new ConfigurationContractValidator();

            // Validate form structure first
            $formValidation     = $validator->validateFormFieldStructure($fromRequest);
            if (!$formValidation->isValid()) {
                Log::error('SimpleFIN form validation failed', $formValidation->getErrors());

                return redirect()->back()->withErrors($formValidation->getErrorMessages())->withInput();
            }

            // Validate complete configuration contract
            $contractValidation = $validator->validateConfigurationContract($configuration);
            if (!$contractValidation->isValid()) {
                Log::error('SimpleFIN configuration contract validation failed', $contractValidation->getErrors());

                return redirect()->back()->withErrors($contractValidation->getErrorMessages())->withInput();
            }

            if ($contractValidation->hasWarnings()) {
                Log::warning('SimpleFIN configuration contract warnings', $contractValidation->getWarnings());
            }

        }
        $configuration->updateDateRange();
        // Map data option is now user-selectable for SimpleFIN via checkbox

        try {
            $json = json_encode($configuration->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }
        StorageService::storeContent($json);

        session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());

        // set config as complete.
        session()->put(Constants::CONFIG_COMPLETE_INDICATOR, true);
        if ('nordigen' === $configuration->getFlow() || 'spectre' === $configuration->getFlow() || 'simplefin' === $configuration->getFlow()) {
            // at this point, nordigen, spectre, and simplefin are ready for data conversion.
            session()->put(Constants::READY_FOR_CONVERSION, true);
        }

        // always redirect to roles, even if this isn't the step yet
        // for nordigen, spectre, and simplefin, roles will be skipped right away.
        return redirect(route('005-roles.index'));
    }
}
