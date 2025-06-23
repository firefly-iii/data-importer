<?php

/*
 * ConfigurationPostRequest.php
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

namespace App\Http\Request;

use App\Services\Session\Constants;
use Illuminate\Validation\Validator;

/**
 * Class ConfigurationPostRequest
 */
class ConfigurationPostRequest extends Request
{
    /**
     * Verify the request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function getAll(): array
    {
        // Debug: Log raw form data before processing
        app('log')->debug('DEBUG: ConfigurationPostRequest raw form data', [
            'do_import_raw'   => $this->get('do_import') ?? [],
            'accounts_raw'    => $this->get('accounts') ?? [],
            'new_account_raw' => $this->get('new_account') ?? [],
        ]);

        // Decode underscore-encoded account IDs back to original IDs with spaces
        $doImport = $this->get('do_import') ?? [];
        $accounts = $this->get('accounts') ?? [];
        $newAccount = $this->get('new_account') ?? [];

        $decodedDoImport = [];
        $decodedAccounts = [];
        $decodedNewAccount = [];

        // Decode do_import array keys
        foreach ($doImport as $encodedId => $value) {
            $originalId = str_replace('_', ' ', $encodedId);
            $decodedDoImport[$originalId] = $value;
            app('log')->debug('DEBUG: Decoded do_import', [
                'encoded' => $encodedId,
                'decoded' => $originalId,
                'value'   => $value,
            ]);
        }

        // Decode accounts array keys
        foreach ($accounts as $encodedId => $value) {
            $originalId = str_replace('_', ' ', $encodedId);
            $decodedAccounts[$originalId] = $value;
            app('log')->debug('DEBUG: Decoded accounts', [
                'encoded' => $encodedId,
                'decoded' => $originalId,
                'value'   => $value,
            ]);
        }

        // Decode new_account array keys
        foreach ($newAccount as $encodedId => $accountData) {
            $originalId = str_replace('_', ' ', $encodedId);
            $decodedNewAccount[$originalId] = $accountData;
            app('log')->debug('DEBUG: Decoded new_account', [
                'encoded' => $encodedId,
                'decoded' => $originalId,
                'data'    => $accountData,
            ]);
        }

        return [
            'headers'                       => $this->convertBoolean($this->get('headers')),
            'delimiter'                     => $this->convertToString('delimiter'),
            'date'                          => $this->convertToString('date'),
            'default_account'               => $this->convertToInteger('default_account'),
            'rules'                         => $this->convertBoolean($this->get('rules')),
            'ignore_duplicate_lines'        => $this->convertBoolean(
                $this->get('ignore_duplicate_lines')
            ),
            'ignore_duplicate_transactions' => $this->convertBoolean(
                $this->get('ignore_duplicate_transactions')
            ),
            'skip_form'                     => $this->convertBoolean($this->get('skip_form')),
            'add_import_tag'                => $this->convertBoolean(
                $this->get('add_import_tag')
            ),
            'specifics'                     => [],
            'roles'                         => [],
            'mapping'                       => [],
            'do_mapping'                    => [],
            'flow'                          => $this->convertToString('flow'),
            'content_type'                  => $this->convertToString('content_type'),
            'custom_tag'                    => $this->convertToString('custom_tag'),

            // duplicate detection:
            'duplicate_detection_method'    => $this->convertToString(
                'duplicate_detection_method'
            ),
            'unique_column_index'           => $this->convertToInteger(
                'unique_column_index'
            ),
            'unique_column_type'            => $this->convertToString(
                'unique_column_type'
            ),

            // spectre values:
            'connection'                 => $this->convertToString('connection'),
            'identifier'                 => $this->convertToString('identifier'),
            'ignore_spectre_categories'  => $this->convertBoolean(
                $this->get('ignore_spectre_categories')
            ),

            // nordigen:
            'nordigen_country'           => $this->convertToString('nordigen_country'),
            'nordigen_bank'              => $this->convertToString('nordigen_bank'),
            'nordigen_max_days'          => $this->convertToString('nordigen_max_days'),
            'nordigen_requisitions'      =>
                json_decode(
                    $this->convertToString('nordigen_requisitions'),
                    true
                ) ?? [],

            // nordigen + spectre - with decoded account IDs
            'do_import'                 => $decodedDoImport,
            'accounts'                  => $decodedAccounts,
            'new_account'               => $decodedNewAccount,
            'map_all_data'          => $this->convertBoolean($this->get('map_all_data')),
            'date_range'            => $this->convertToString('date_range'),
            'date_range_number'     => $this->convertToInteger('date_range_number'),
            'date_range_unit'       => $this->convertToString('date_range_unit'),
            'date_not_before'       => $this->getCarbonDate('date_not_before'),
            'date_not_after'        => $this->getCarbonDate('date_not_after'),

            // utf8 conversion
            'conversion'            => $this->convertBoolean($this->get('conversion')),

            // camt
            'grouped_transaction_handling' => $this->convertToString(
                'grouped_transaction_handling'
            ),
            'use_entire_opposing_address'  => $this->convertBoolean(
                $this->get('use_entire_opposing_address')
            ),
        ];
    }

    public function rules(): array
    {
        $flow = request()->cookie(Constants::FLOW_COOKIE);
        $columnOptions = implode(
            ',',
            array_keys(config('csv.unique_column_options'))
        );
        if ('nordigen' === $flow) {
            $columnOptions = implode(
                ',',
                array_keys(config('nordigen.unique_column_options'))
            );
        }
        if ('simplefin' === $flow) {
            $columnOptions = implode(
                ',',
                array_keys(config('simplefin.unique_column_options'))
            );
        }

        return [
            'headers'                       => 'numeric|between:0,1',
            'delimiter'                     => 'in:comma,semicolon,tab',
            'date'                          => 'between:1,25',
            'default_account'               =>
                'simplefin' === $flow
                    ? 'nullable|numeric|min:1|max:100000'
                    : 'required|numeric|min:1|max:100000',
            'rules'                         => 'numeric|between:0,1',
            'ignore_duplicate_lines'        => 'numeric|between:0,1',
            'ignore_duplicate_transactions' => 'numeric|between:0,1',
            'skip_form'                     => 'numeric|between:0,1',
            'add_import_tag'                => 'numeric|between:0,1',
            'ignore_spectre_categories'     => 'numeric|between:0,1',

            // duplicate detection:
            'duplicate_detection_method'    => 'in:cell,none,classic',
            'unique_column_index'           => 'numeric',
            'unique_column_type'            => sprintf('in:%s', $columnOptions),

            // conversion
            'conversion'                    => 'numeric|between:0,1',

            // new account creation - updated to handle underscore-encoded field names
            'new_account.*.name'            => 'nullable|string|max:255',
            'new_account.*.create'          => 'nullable|string|in:0,1',
            'new_account.*.type'            =>
                'nullable|string|in:asset,liability,expense,revenue',
            'new_account.*.currency'        =>
                'nullable|string|size:3|regex:/^[A-Z]{3}$/',
            'new_account.*.opening_balance' => 'nullable|numeric',

            // camt
            'grouped_transaction_handling'  => 'in:single,group,split',
            'use_entire_opposing_address'   => 'numeric|between:0,1',
        ];
    }

    /**
     * Configure the validator instance with special rules for after the basic validation rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // validate all account info
            $flow = request()->cookie(Constants::FLOW_COOKIE);
            $data = $validator->getData();
            $doImport = $data['do_import'] ?? [];
            if (0 === count($doImport) && 'file' !== $flow) {
                $validator
                    ->errors()
                    ->add(
                        'do_import',
                        'You must select at least one account to import from.'
                    );
            }

            // validate new account creation data - both accounts and new_account now use encoded field names
            $accounts = $data['accounts'] ?? [];
            $newAccounts = $data['new_account'] ?? [];

            app('log')->debug('DEBUG: withValidator account validation', [
                'accounts'    => $accounts,
                'newAccounts' => array_keys($newAccounts),
                'flow'        => $flow,
            ]);

            foreach ($accounts as $encodedAccountId => $selectedValue) {
                if ($selectedValue === 'create_new') {
                    app('log')->debug(
                        'DEBUG: Validating new account creation',
                        [
                            'encodedAccountId' => $encodedAccountId,
                            'selectedValue'    => $selectedValue,
                            'hasNameField'     => isset(
                                $newAccounts[$encodedAccountId]['name']
                            ),
                            'hasCreateField'   => isset(
                                $newAccounts[$encodedAccountId]['create']
                            ),
                            'nameValue'        =>
                                $newAccounts[$encodedAccountId]['name'] ??
                                'NOT_SET',
                            'createValue'      =>
                                $newAccounts[$encodedAccountId]['create'] ??
                                'NOT_SET',
                        ]
                    );

                    // Validate that account name is provided and create flag is set
                    // Both arrays now use encoded keys, so they should match directly
                    if (
                        !isset($newAccounts[$encodedAccountId]['name']) ||
                        empty(trim($newAccounts[$encodedAccountId]['name']))
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                "new_account.{$encodedAccountId}.name",
                                'Account name is required when creating a new account.'
                            );
                    }
                    if (
                        !isset($newAccounts[$encodedAccountId]['create']) ||
                        $newAccounts[$encodedAccountId]['create'] !== '1'
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                "new_account.{$encodedAccountId}.create",
                                'Create flag must be set for new account creation.'
                            );
                    }
                }
            }
        });
    }
}
