<?php

/*
 * RoleController.php
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

declare(strict_types=1);

namespace App\Http\Controllers\Import\File;

use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\RoleControllerMiddleware;
use App\Http\Request\RolesPostRequest;
use App\Services\CSV\Roles\RoleService;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Storage\StorageService;
use App\Support\Http\RestoresConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Class RoleController
 */
class RoleController extends Controller
{
    use RestoresConfiguration;

    /**
     * RoleController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Define roles');
        $this->middleware(RoleControllerMiddleware::class);
    }

    /**
     * @return View|void
     */
    public function index(Request $request)
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $flow          = $request->cookie(Constants::FLOW_COOKIE);
        if ('file' !== $flow) {
            exit('redirect or something');
        }
        // get configuration object.
        $configuration = $this->restoreConfiguration();
        $contentType   = $configuration->getContentType();

        if ('csv' === $contentType) {
            return $this->csvIndex($request, $configuration);
        }
        if ('camt' === $contentType) {
            return $this->camtIndex($request, $configuration);
        }

        throw new ImporterErrorException(sprintf('Cannot handle file type "%s"', $contentType));
    }

    private function csvIndex(Request $request, Configuration $configuration): View
    {
        $mainTitle           = 'Role definition';
        $subTitle            = 'Configure the role of each column in your file';
        $sessionUploadFile   = session()->get(Constants::UPLOAD_DATA_FILE);
        if (null === $sessionUploadFile) {
            Log::error('No data file in session, give big fat error.');
            Log::error('This often happens when you access the data importer over its IP:port combo. Not all browsers like this.');

            throw new ImporterErrorException('The Firefly III data importer forgot where your uploaded data is. This may happen when cookies get lost. Please check the logs for more info.');
        }


        // get columns from file
        $content             = StorageService::getContent($sessionUploadFile, $configuration->isConversion());
        $columns             = RoleService::getColumns($content, $configuration);
        $examples            = RoleService::getExampleData($content, $configuration);

        // submit mapping from config.
        $mapping             = base64_encode(json_encode($configuration->getMapping(), JSON_THROW_ON_ERROR));

        // roles
        $roles               = config('csv.import_roles');
        ksort($roles);

        // configuration (if it is set)
        $configuredRoles     = $configuration->getRoles();
        $configuredDoMapping = $configuration->getDoMapping();

        return view('import.005-roles.index-csv', compact('mainTitle', 'configuration', 'subTitle', 'columns', 'examples', 'roles', 'configuredRoles', 'configuredDoMapping', 'mapping'));
    }

    private function camtIndex(Request $request, Configuration $configuration): View
    {
        $mainTitle         = 'Role definition';
        $subTitle          = 'Configure the role of each field in your camt.053 file';

        $sessionUploadFile = session()->get(Constants::UPLOAD_DATA_FILE);

        if (null === $sessionUploadFile) {
            Log::error('No data file in session, give big fat error.');
            Log::error('This often happens when you access the data importer over its IP:port combo. Not all browsers like this.');

            throw new ImporterErrorException('The Firefly III data importer forgot where your uploaded data is. This may happen when cookies get lost. Please check the logs for more info.');
        }

        // get example data from file.
        $content           = StorageService::getContent($sessionUploadFile, $configuration->isConversion());
        $examples          = RoleService::getExampleDataFromCamt($content, $configuration);
        $roles             = $configuration->getRoles();
        $doMapping         = $configuration->getDoMapping();
        // four levels in a CAMT file, level A B C D. Each level has a pre-defined set of
        // available fields and information.
        $levels            = [];
        $levels['A']       = [
            'title'       => trans('camt.level_A'),
            'explanation' => trans('camt.explain_A'),
            'fields'      => $this->getFieldsForLevel('A'),
        ];
        $levels['B']       = [
            'title'       => trans('camt.level_B'),
            'explanation' => trans('camt.explain_B'),
            'fields'      => $this->getFieldsForLevel('B'),
        ];
        $levels['C']       = [
            'title'       => trans('camt.level_C'),
            'explanation' => trans('camt.explain_C'),
            'fields'      => [
                // have to collect C by hand because of intermediate sections
                'entryAccountServicerReference' => config('camt.fields.entryAccountServicerReference'),
                'entryReference'                => config('camt.fields.entryReference'),
                'entryAdditionalInfo'           => config('camt.fields.entryAdditionalInfo'),
                'section_transaction'           => ['section' => true, 'title' => 'transaction'],
                'entryAmount'                   => config('camt.fields.entryAmount'),
                'entryAmountCurrency'           => config('camt.fields.entryAmountCurrency'),
                'entryValueDate'                => config('camt.fields.entryValueDate'),
                'entryBookingDate'              => config('camt.fields.entryBookingDate'),
                'section_btc'                   => ['section' => true, 'title' => 'Btc'],
                'entryBtcDomainCode'            => config('camt.fields.entryBtcDomainCode'),
                'entryBtcFamilyCode'            => config('camt.fields.entryBtcFamilyCode'),
                'entryBtcSubFamilyCode'         => config('camt.fields.entryBtcSubFamilyCode'),
            ],
        ];
        $group_handling    = $configuration->getGroupedTransactionHandling();
        if ('group' === $group_handling) {
            $levels['D'] = [
                'title'       => trans('camt.level_D'),
                'explanation' => trans('camt.explain_D_dropped'),
            ];
        }
        if ('group' !== $group_handling) {
            $levels['D'] = [
                'title'       => trans('camt.level_D'),
                'explanation' => trans('camt.explain_D'),
                'fields'      => [
                    // have to collect D by hand because of intermediate sections
                    'entryDetailAccountServicerReference'                                            => config('camt.fields.entryDetailAccountServicerReference'),
                    'entryDetailRemittanceInformationUnstructuredBlockMessage'                       => config('camt.fields.entryDetailRemittanceInformationUnstructuredBlockMessage'),
                    'entryDetailRemittanceInformationStructuredBlockAdditionalRemittanceInformation' => config('camt.fields.entryDetailRemittanceInformationStructuredBlockAdditionalRemittanceInformation'),
                    'section_tr'                                                                     => ['section' => true, 'title' => 'transaction'],
                    'entryDetailAmount'                                                              => config('camt.fields.entryDetailAmount'),
                    'entryDetailAmountCurrency'                                                      => config('camt.fields.entryDetailAmountCurrency'),
                    'section_btc'                                                                    => ['section' => true, 'title' => 'Btc'],
                    'entryDetailBtcDomainCode'                                                       => config('camt.fields.entryDetailBtcDomainCode'),
                    'entryDetailBtcFamilyCode'                                                       => config('camt.fields.entryDetailBtcFamilyCode'),
                    'entryDetailBtcSubFamilyCode'                                                    => config('camt.fields.entryDetailBtcSubFamilyCode'),
                    'section_opposing'                                                               => ['section' => true, 'title' => 'opposingPart'],
                    'entryDetailOpposingAccountIban'                                                 => config('camt.fields.entryDetailOpposingAccountIban'),
                    'entryDetailOpposingAccountNumber'                                               => config('camt.fields.entryDetailOpposingAccountNumber'),
                    'entryDetailOpposingName'                                                        => config('camt.fields.entryDetailOpposingName'),
                ],
            ];
        }

        return view('import.005-roles.index-camt', compact('mainTitle', 'configuration', 'subTitle', 'levels', 'doMapping', 'examples', 'roles'));
    }

    private function getFieldsForLevel(string $level): array
    {
        $allFields = config('camt.fields');

        return array_filter($allFields, function ($field) use ($level) {
            return $level === $field['level'];
        });
    }

    public function postIndex(RolesPostRequest $request): RedirectResponse
    {
        // the request object must be able to handle all file types.
        $configuration = $this->restoreConfiguration();
        return $this->processPostIndex($request, $configuration);
    }

    private function processPostIndex(RolesPostRequest $request, Configuration $configuration): RedirectResponse
    {
        $data           = $request->getAllForFile();
        $needsMapping   = $this->needMapping($data['do_mapping']);
        $configuration->setRoles($data['roles']);
        $configuration->setDoMapping($data['do_mapping']);

        session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());

        // then this is the new, full array:
        $fullArray      = $configuration->toArray();

        // and it can be saved on disk:
        $configFileName = StorageService::storeArray($fullArray);
        Log::debug(sprintf('Old configuration was stored under key "%s".', session()->get(Constants::UPLOAD_CONFIG_FILE)));

        // this is a new config file name.
        session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);

        Log::debug(sprintf('New configuration is stored under key "%s".', session()->get(Constants::UPLOAD_CONFIG_FILE)));

        // set role config as complete.
        session()->put(Constants::ROLES_COMPLETE_INDICATOR, true);

        // redirect to mapping thing.
        if (true === $needsMapping) {
            return redirect()->route('006-mapping.index');
        }
        // otherwise, store empty mapping, and continue:
        // set map config as complete.
        session()->put(Constants::READY_FOR_CONVERSION, true);

        return redirect()->route('007-convert.index');
    }

    /**
     * Will tell you if any role needs mapping.
     */
    private function needMapping(array $array): bool
    {
        $need = false;
        foreach ($array as $value) {
            if (true === $value) {
                $need = true;
            }
        }

        return $need;
    }
}
