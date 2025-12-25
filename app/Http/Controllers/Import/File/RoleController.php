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
use App\Http\Request\RolesPostRequest;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\CSV\Roles\RoleService;
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

    private ImportJobRepository $repository;

    /**
     * RoleController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Define roles');
        $this->repository = new ImportJobRepository();
    }

    /**
     * @return View|void
     */
    public function index(Request $request, string $identifier)
    {
        $importJob = $this->repository->find($identifier);
        $flow      = $importJob->getFlow();
        $mainTitle = 'Role definition';
        $subTitle  = 'Data role definition';
        if ('file' !== $flow) {
            return view('import.005-roles.no-define-roles')->with(compact('flow', 'mainTitle', 'subTitle'));
        }
        $state = $importJob->getState();
        if ('new' === $state || 'contains_content' === $state || 'is_parsed' === $state) {
            exit(sprintf('Job is in state "%s" so not ready for this step. Needs a better page.', $state));
        }

        $warning = '';
        if ('configured_and_roles_defined' === $importJob->getState() || 'configured_roles_map_in_place' === $importJob->getState()) {
            $warning = trans('import.roles_defined_warning');
        }

        // get configuration object.
        $configuration = $importJob->getConfiguration();
        $contentType   = $configuration->getContentType();

        if ('csv' === $contentType) {
            return $this->csvIndex($importJob, $warning);
        }
        if ('camt' === $contentType) {
            return $this->camtIndex($importJob, $warning);
        }

        throw new ImporterErrorException(sprintf('Cannot handle file type "%s"', $contentType));
    }

    private function csvIndex(ImportJob $importJob, string $warning): View
    {
        $mainTitle = 'Role definition';
        $subTitle  = 'Configure the role of each column in your file';
        // get columns from file
        $identifier    = $importJob->identifier;
        $configuration = $importJob->getConfiguration();
        $content       = $importJob->getImportableFileString();
        $columns       = RoleService::getColumns($content, $configuration);
        $exampleData   = RoleService::getExampleData($content, $configuration);

        // Extract column examples and pseudo identifier examples
        $examples       = $exampleData['columns'];
        $pseudoExamples = $exampleData['pseudo_identifier'];
        $ignoreWarnings = '1' === request()->old('ignore_warnings');
        // roles
        $roles = config('csv.import_roles');
        ksort($roles);

        // configuration (if it is set)
        $configuredRoles     = $configuration->getRoles();
        $configuredDoMapping = $configuration->getDoMapping();
        $old                 = request()->old('roles');
        if (null !== $old && count($old) > 0) {
            $configuredRoles = $old;
        }
        return view('import.005-roles.index-csv', compact('mainTitle', 'warning', 'ignoreWarnings', 'identifier', 'configuration', 'subTitle', 'columns', 'examples', 'pseudoExamples', 'roles', 'configuredRoles', 'configuredDoMapping'));
    }

    private function camtIndex(ImportJob $importJob, string $warning): View
    {
        $mainTitle     = 'Role definition';
        $identifier    = $importJob->identifier;
        $configuration = $importJob->getConfiguration();
        $camtType      = $configuration->getCamtType();
        $subTitle      = sprintf('Configure the role of each field in your camt.%s file', $camtType);

        // get example data from file.
        $examples  = RoleService::getExampleDataFromCamt($importJob->getImportableFileString(), $configuration);
        $roles     = $configuration->getRoles();
        $doMapping = $configuration->getDoMapping();
        // four levels in a CAMT file, level A B C D. Each level has a pre-defined set of
        // available fields and information.
        $levels      = [];
        $levels['A'] = [
            'title'       => trans('camt.level_A'),
            'explanation' => trans('camt.explain_A'),
            'fields'      => $this->getFieldsForLevel('A'),
        ];
        $levels['B'] = [
            'title'       => trans('camt.level_B'),
            'explanation' => trans('camt.explain_B'),
            'fields'      => $this->getFieldsForLevel('B'),
        ];
        //        var_dump($levels['B']);
        //        var_dump($roles);
        //        exit;
        $levels['C']    = [
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
        $group_handling = $configuration->getGroupedTransactionHandling();
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
                    'entryDetailEndToEndId'                                                          => config('camt.fields.entryDetailEndToEndId'),
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

        $old = request()->old('roles');
        if (null !== $old && count($old) > 0) {
            $roles = $old;
        }

        $levels = $this->mergeLevelsAndRoles($levels, $roles);

        return view('import.005-roles.index-camt', compact('mainTitle', 'warning', 'identifier', 'configuration', 'subTitle', 'levels', 'doMapping', 'examples', 'roles'));
    }

    private function getFieldsForLevel(string $level): array
    {
        $allFields = config('camt.fields');

        return array_filter($allFields, fn($field) => $level === $field['level']);
    }

    private function mergeLevelsAndRoles(array $levels, array $roles): array
    {
        Log::debug('Now in mergeLevelsAndRoles');
        foreach ($levels as $letter => $info) {
            Log::debug(sprintf('Now at level %s', $letter));
            foreach ($info['fields'] as $index => $field) {
                $title         = $field['title'];
                $selected      = $field['default_role'] ?? '_impossible';
                $possibleRoles = [];
                Log::debug(sprintf('Analysing level "%s" field "%s"', $letter, $title));
                if (array_key_exists('roles', $field)) {
                    $possibleRoles = array_keys(config(sprintf('camt.roles.%s', $field['roles'])) ?? []);
                }
                if (array_key_exists($title, $roles)) {
                    Log::debug(sprintf('Start: User has role "%s" pre-selected for level %s field "%s"', $roles[$title], $letter, $title));
                    $selected = $roles[$title];
                    if (!in_array($selected, $possibleRoles)) {
                        $selected = '_ignore';
                        Log::debug('User selected impossible role, will be ignored.');
                    }
//                    if ('_ignore' === $roles[$title]) {
//                        $selected = '_ignore';
//                        Log::debug(sprintf('Make default role "_ignore" for level %s field "%s"', $letter, $title));
//                    }
//                    if ($roles[$title] === $field['default_role']) {
//                        // $selected = $field['default_role'];
//                        Log::debug(sprintf('User has selected role "%s" for level %s field "%s"', $roles[$title], $letter, $title));
//                    }
                }
                if (!array_key_exists($title, $roles)) {
                    Log::debug(sprintf('User has no role pre-selected for level %s field "%s"', $letter, $title));
                }
                $levels[$letter]['fields'][$index]['selected'] = $selected;
            }
        }

        return $levels;
    }

    public function postIndex(RolesPostRequest $request, string $identifier): RedirectResponse
    {
        $importJob     = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();
        $data          = $request->getAllForFile();
        $needsMapping  = $this->needMapping($data['do_mapping']);
        $configuration->setRoles($data['roles']);
        $configuration->setDoMapping($data['do_mapping']);

        if (false === $needsMapping) {
            // job needs no data mapping, so the state can be set:
            $importJob->setState('configured_roles_map_in_place');
        }
        if (true === $needsMapping) {
            // needs mapping still:
            $importJob->setState('configured_and_roles_defined');
        }

        $importJob->setConfiguration($configuration);
        $this->repository->saveToDisk($importJob);

        if (true === $needsMapping) {
            // redirect to the route to set mapping:
            return redirect()->route('data-mapping.index', [$identifier]);
        }

        return redirect()->route('data-conversion.index', [$identifier]);
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
