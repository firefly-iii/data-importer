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

use App\Repository\ImportJob\ImportJobRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;

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
        // Decode underscore-encoded account IDs back to original IDs with spaces
        $doImport    = $this->get('do_import') ?? [];
        $accounts    = $this->get('accounts') ?? [];
        $newAccounts = $this->get('new_accounts') ?? [];

        // loop $accounts array, because it is always complete and present.
        $toImportFrom = [];
        $toCreate     = [];
        foreach ($accounts as $identifier => $current) {
            // import from these accounts?
            // if it must be created, details MUST be in "newAccounts, or it will be skipped.
            if (array_key_exists($identifier, $doImport) && 'create_new' === $accounts[$identifier] ?? false) {
                $toImportFrom[$identifier] = 0;
                $toCreate[$identifier]     = $newAccounts[$identifier];
            }
            if (array_key_exists($identifier, $doImport) && (int)$accounts[$identifier] > 0) {
                $toImportFrom[$identifier] = (int)$accounts[$identifier];
            }
        }

        // calculate dates
        $notBefore = $this->getCarbonDate('date_not_before');
        $notAfter  = $this->getCarbonDate('date_not_after');

        // reverse dates if they are bla bla bla.
        if ($notBefore instanceof Carbon && $notAfter instanceof Carbon) {
            if ($notBefore->gt($notAfter)) {
                // swap them
                [$notBefore, $notAfter] = [$notAfter->copy(), $notBefore->copy()];
            }
        }

        return [
            'headers'                       => $this->convertBoolean($this->get('headers')),
            'delimiter'                     => $this->convertToString('delimiter'),
            'date'                          => $this->convertToString('date'),
            'default_account'               => $this->convertToInteger('default_account'),
            'rules'                         => $this->convertBoolean($this->get('rules')),
            'ignore_duplicate_lines'        => $this->convertBoolean($this->get('ignore_duplicate_lines')),
            'ignore_duplicate_transactions' => $this->convertBoolean($this->get('ignore_duplicate_transactions')),
            'skip_form'                     => $this->convertBoolean($this->get('skip_form')),
            'add_import_tag'                => $this->convertBoolean($this->get('add_import_tag')),
            'pending_transactions'          => $this->convertBoolean($this->get('pending_transactions')),
            'custom_tag'                    => $this->convertToString('custom_tag'),

            // duplicate detection:
            'duplicate_detection_method'    => $this->convertToString('duplicate_detection_method'),
            'unique_column_index'           => $this->parseUniqueColumnIndex(),
            'unique_column_type'            => $this->convertToString('unique_column_type'),
            'pseudo_identifier'             => $this->buildPseudoIdentifier(),

            // spectre values:
            'ignore_spectre_categories'     => $this->convertBoolean($this->get('ignore_spectre_categories')),

            // nordigen + spectre - with decoded account IDs
            'to_import_from'                => $toImportFrom,
            'to_create'                     => $toCreate,
            'map_all_data'                  => $this->convertBoolean($this->get('map_all_data')),
            'date_range'                    => $this->convertToString('date_range'),
            'date_range_number'             => $this->convertToInteger('date_range_number'),
            'date_range_unit'               => $this->convertToString('date_range_unit'),
            'date_range_not_after_number'   => $this->convertToInteger('date_range_not_after_number'),
            'date_range_not_after_unit'     => $this->convertToString('date_range_not_after_unit'),
            'date_not_before'               => $notBefore,
            'date_not_after'                => $notAfter,

            // utf8 conversion
            'conversion'                    => $this->convertBoolean($this->get('conversion')),

            // camt
            'grouped_transaction_handling'  => $this->convertToString('grouped_transaction_handling'),
            'use_entire_opposing_address'   => $this->convertBoolean($this->get('use_entire_opposing_address')),
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
        if ('' === trim((string)$raw)) {
            return 0;
        }

        // If it contains comma, parse as array and return first element
        if (str_contains((string)$raw, ',')) {
            $indices = array_map(trim(...), explode(',', (string)$raw));
            $indices = array_filter($indices, is_numeric(...));
            $indices = array_map(intval(...), $indices);

            return count($indices) > 0 ? (int)reset($indices) : 0;
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
        $indices = array_map(trim(...), explode(',', (string)$raw));
        $indices = array_filter($indices, is_numeric(...));
        $indices = array_map(intval(...), $indices);

        // Need at least 1 column
        if (0 === count($indices)) {
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
        $repository         = new ImportJobRepository();
        $identifier         = request()->route()->parameter('identifier');
        $importJob          = $repository->find($identifier);
        $flow               = $importJob->getFlow();
        $columnOptions      = $this->getColumnOptions($flow);
        $defaultAccountRule = $this->getDefaultAccountRule($flow);

        return [
            'headers'                       => 'numeric|between:0,1',
            'delimiter'                     => 'in:comma,semicolon,tab',
            'date'                          => 'between:1,25',
            'default_account'               => $defaultAccountRule,
            'rules'                         => 'numeric|between:0,1',
            'ignore_duplicate_lines'        => 'numeric|between:0,1',
            'ignore_duplicate_transactions' => 'numeric|between:0,1',
            'skip_form'                     => 'numeric|between:0,1',
            'add_import_tag'                => 'numeric|between:0,1',
            'ignore_spectre_categories'     => 'numeric|between:0,1',

            // simplefin data rules"
            'do_import.*'                   => 'numeric|between:0,1',
            'accounts.*'                    => 'required|min:0|max:100',
            'new_accounts.*'                => 'required|array',
            'new_accounts.*.create'         => 'required|numeric|between:0,1',
            'new_accounts.*.name'           => 'required|min:0|max:100',
            'new_accounts.*.type'           => 'required|in:asset,liabilities',
            'new_accounts.*.currency'       => 'required|min:3|max:12',

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
            'new_account.*.currency'        => 'nullable|string|size:3|regex:/^[A-Z]{7}$/',
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
//        $validator->after(function (Validator $validator): void {
//            $repository = new ImportJobRepository();
//            $identifier = request()->route()->parameter('identifier');
//            $importJob  = $repository->find($identifier);
//            $flow       = $importJob->getFlow();
//            $data       = $validator->getData();
//        });
    }

    private function getColumnOptions(string $flow): string
    {
        if ('nordigen' === $flow) {
            return implode(',', array_keys(config('nordigen.unique_column_options')));
        }
        if ('simplefin' === $flow) {
            return implode(',', array_keys(config('simplefin.unique_column_options')));
        }
        return implode(',', array_keys(config('file.unique_column_options')));
    }

    private function getDefaultAccountRule(string $flow): string
    {
        return 'simplefin' === $flow ? 'nullable|numeric|min:1|max:100000' : 'required|numeric|min:1|max:100000';
    }
}
