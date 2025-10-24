<?php

/*
 * ConfigurationPostRequest.php
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

namespace App\Http\Request;

use Carbon\Carbon;
use App\Services\Session\Constants;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Log;

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
        Log::debug('ConfigurationPostRequest raw form data', [
            'do_import_raw'   => $this->get('do_import') ?? [],
            'accounts_raw'    => $this->get('accounts') ?? [],
            'new_account_raw' => $this->get('new_account') ?? [],
        ]);

        // Decode underscore-encoded account IDs back to original IDs with spaces
        $doImport            = $this->get('do_import') ?? [];
        $accounts            = $this->get('accounts') ?? [];
        $newAccounts         = $this->get('new_accounts') ?? [];

        $decodedDoImport     = [];
        $decodedAccounts     = [];
        $decodedNewAccounts  = [];

        // Decode do_import array keys
        foreach ($doImport as $encodedId => $value) {
            $originalId                   = str_replace('_', ' ', (string)$encodedId);
            $decodedDoImport[$originalId] = $value;
            Log::debug('Decoded do_import', [
                'encoded' => (string)$encodedId,
                'decoded' => $originalId,
                'value'   => $value,
            ]);
        }

        // Decode accounts array keys
        foreach ($accounts as $encodedId => $value) {
            $originalId                   = str_replace('_', ' ', (string)$encodedId);
            $decodedAccounts[$originalId] = $value;
            Log::debug('Decoded accounts', [
                'encoded' => (string)$encodedId,
                'decoded' => $originalId,
                'value'   => $value,
            ]);
        }

        // Decode new_accounts array keys
        foreach ($newAccounts as $encodedId => $accountData) {
            $originalId                      = str_replace('_', ' ', (string)$encodedId);
            $decodedNewAccounts[$originalId] = $accountData;
            Log::debug('Decoded new_account', [
                'encoded' => (string)$encodedId,
                'decoded' => $originalId,
                'data'    => $accountData,
            ]);
        }
        $notBefore           = $this->getCarbonDate('date_not_before');
        $notAfter            = $this->getCarbonDate('date_not_after');

        // reverse dates if they are bla bla bla.
        if ($notBefore instanceof Carbon && $notAfter instanceof Carbon) {
            if ($notBefore->gt($notAfter)) {
                // swap them
                [$notBefore, $notAfter] = [$notAfter->copy(), $notBefore->copy()];
            }
        }

        // loop accounts:
        $accounts            = [];
        $toCreateNewAccounts = [];

        foreach (array_keys($decodedDoImport) as $identifier) {
            if (array_key_exists($identifier, $decodedAccounts)) {
                $accountValue          = (int)$decodedAccounts[$identifier];
                $accounts[$identifier] = $accountValue;
            }
            if (array_key_exists($identifier, $decodedNewAccounts)) {
                // this is a new account to create.
                $toCreateNewAccounts[$identifier] = $decodedNewAccounts[$identifier];
            }
            if (!array_key_exists($identifier, $decodedAccounts)) {
                Log::warning(sprintf('Account identifier %s in do_import but not in accounts array', $identifier));
            }
        }

        return [
            'headers'                                  => $this->convertBoolean($this->get('headers')),
            'delimiter'                                => $this->convertToString('delimiter'),
            'date'                                     => $this->convertToString('date'),
            'default_account'                          => $this->convertToInteger('default_account'),
            'rules'                                    => $this->convertBoolean($this->get('rules')),
            'ignore_duplicate_lines'                   => $this->convertBoolean($this->get('ignore_duplicate_lines')),
            'ignore_duplicate_transactions'            => $this->convertBoolean($this->get('ignore_duplicate_transactions')),
            'skip_form'                                => $this->convertBoolean($this->get('skip_form')),
            'add_import_tag'                           => $this->convertBoolean($this->get('add_import_tag')),
            'pending_transactions'                     => $this->convertBoolean($this->get('pending_transactions')),
            'specifics'                                => [],
            'roles'                                    => [],
            'mapping'                                  => [],
            'do_mapping'                               => [],
            'flow'                                     => $this->convertToString('flow'),
            'content_type'                             => $this->convertToString('content_type'),
            'custom_tag'                               => $this->convertToString('custom_tag'),

            // duplicate detection:
            'duplicate_detection_method'               => $this->convertToString('duplicate_detection_method'),
            'unique_column_index'                      => $this->parseUniqueColumnIndex(),
            'unique_column_type'                       => $this->convertToString('unique_column_type'),
            'pseudo_identifier'                        => $this->buildPseudoIdentifier(),

            // spectre values:
            'connection'                               => $this->convertToString('connection'),
            'identifier'                               => $this->convertToString('identifier'),
            'ignore_spectre_categories'                => $this->convertBoolean($this->get('ignore_spectre_categories')),

            // nordigen:
            'nordigen_country'                         => $this->convertToString('nordigen_country'),
            'nordigen_bank'                            => $this->convertToString('nordigen_bank'),
            'nordigen_max_days'                        => $this->convertToString('nordigen_max_days'),
            'nordigen_requisitions'                    => json_decode($this->convertToString('nordigen_requisitions'), true) ?? [],

            // nordigen + spectre - with decoded account IDs
            'do_import'                                => $decodedDoImport,
            'accounts'                                 => $accounts,
            'new_accounts'                             => $toCreateNewAccounts,
            'map_all_data'                             => $this->convertBoolean($this->get('map_all_data')),
            'date_range'                               => $this->convertToString('date_range'),
            'date_range_number'                        => $this->convertToInteger('date_range_number'),
            'date_range_unit'                          => $this->convertToString('date_range_unit'),

            'date_range_not_after_number'              => $this->convertToInteger('date_range_not_after_number'),
            'date_range_not_after_unit'                => $this->convertToString('date_range_not_after_unit'),

            'date_not_before'                          => $notBefore,
            'date_not_after'                           => $notAfter,

            // simplefin:
            'access_token'                             => $this->convertToString('access_token'),

            // utf8 conversion
            'conversion'                               => $this->convertBoolean($this->get('conversion')),

            // camt
            'grouped_transaction_handling'             => $this->convertToString('grouped_transaction_handling'),
            'use_entire_opposing_address'              => $this->convertBoolean($this->get('use_entire_opposing_address')),
        ];
    }

    /**
     * Parse unique_column_index from either single integer or comma-separated string.
     * Returns the first index for backward compatibility with existing code.
     */
    private function parseUniqueColumnIndex(): int
    {
        $raw = $this->get('unique_column_index', '0');

        // Handle empty input
        if (trim($raw) === '') {
            return 0;
        }

        // If it contains comma, parse as array and return first element
        if (str_contains((string)$raw, ',')) {
            $indices = array_map('trim', explode(',', (string)$raw));
            $indices = array_filter($indices, 'is_numeric');
            $indices = array_map('intval', $indices);

            return !empty($indices) ? (int)reset($indices) : 0;
        }

        // Single value - convert to integer
        return (int)$raw;
    }

    /**
     * Build pseudo identifier definition for identifier-based detection.
     * This unifies single and multiple column identifiers into one mechanism.
     */
    private function buildPseudoIdentifier(): array
    {
        // Only build pseudo identifier if using 'cell' detection method
        if ('cell' !== $this->convertToString('duplicate_detection_method')) {
            return [];
        }

        $raw = $this->get('unique_column_index', '0');

        // Parse column indices (handles both single "0" and multiple "0,3,5")
        $indices = array_map('trim', explode(',', (string)$raw));
        $indices = array_filter($indices, 'is_numeric');
        $indices = array_map('intval', $indices);

        // Need at least 1 column
        if (empty($indices)) {
            return [];
        }

        // Build pseudo identifier definition (same for single or multiple columns)
        $type = $this->convertToString('unique_column_type');

        return [
            'source_columns' => $indices,
            'separator'      => '|',
            'role'           => $type,
        ];
    }

    public function rules(): array
    {
        $flow          = request()->cookie(Constants::FLOW_COOKIE);
        $columnOptions = implode(',', array_keys(config('file.unique_column_options')));
        if ('nordigen' === $flow) {
            $columnOptions = implode(',', array_keys(config('nordigen.unique_column_options')));
        }
        if ('simplefin' === $flow) {
            $columnOptions = implode(',', array_keys(config('simplefin.unique_column_options')));
        }

        return [
            'headers'                       => 'numeric|between:0,1',
            'delimiter'                     => 'in:comma,semicolon,tab',
            'date'                          => 'between:1,25',
            'default_account'               => 'simplefin' === $flow
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
            'unique_column_index'           => 'nullable|string',  // Allow comma-separated indices
            'unique_column_type'            => sprintf('in:%s', $columnOptions),

            // conversion
            'conversion'                    => 'numeric|between:0,1',

            // new account creation - updated to handle underscore-encoded field names
            'new_account.*.name'            => 'nullable|string|max:255',
            'new_account.*.create'          => 'nullable|string|in:0,1',
            'new_account.*.type'            => 'nullable|string|in:asset,liability,expense,revenue',
            'new_account.*.currency'        => 'nullable|string|size:3|regex:/^[A-Z]{3}$/',
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
            $flow        = request()->cookie(Constants::FLOW_COOKIE);
            $data        = $validator->getData(); // @phpstan-ignore-line
            $doImport    = $data['do_import'] ?? [];
            if (0 === count($doImport) && 'file' !== $flow) {
                $validator->errors()->add('do_import', 'You must select at least one account to import from.');
            }

            // validate new account creation data - both accounts and new_account now use encoded field names
            $accounts    = $data['accounts'] ?? [];
            $newAccounts = $data['new_account'] ?? [];

            Log::debug('withValidator account validation', [
                'accounts'    => $accounts,
                'newAccounts' => array_keys($newAccounts),
                'flow'        => $flow,
            ]);

            foreach ($accounts as $encodedAccountId => $selectedValue) {
                if ('create_new' === $selectedValue) {
                    $hasName   = array_key_exists($encodedAccountId, $newAccounts) && array_key_exists('name', $newAccounts[$encodedAccountId]);
                    $hasCreate = array_key_exists($encodedAccountId, $newAccounts) && array_key_exists('create', $newAccounts[$encodedAccountId]);
                    Log::debug(
                        'DEBUG: Validating new account creation',
                        [
                            'encodedAccountId' => $encodedAccountId,
                            'selectedValue'    => $selectedValue,
                            'hasNameField'     => $hasName,
                            'hasCreateField'   => $hasCreate,
                            'nameValue'        => $hasName ? $newAccounts[$encodedAccountId]['name'] : null,
                            'createValue'      => $hasCreate ? $newAccounts[$encodedAccountId]['create'] : null,
                        ]
                    );

                    // Validate that account name is provided and create flag is set
                    // Both arrays now use encoded keys, so they should match directly
                    if ($hasName && '' === (string) $newAccounts[$encodedAccountId]['name']) {
                        $validator->errors()->add(sprintf('new_account.%s.name', $encodedAccountId), 'Account name is required when creating a new account.');
                    }
                    if (!$hasCreate || '1' !== $newAccounts[$encodedAccountId]['create']) {
                        // $validator->errors()->add(sprintf('new_account.%s.create', $encodedAccountId), 'Create flag must be set for new account creation.');
                    }
                }
            }
        });
    }
}
