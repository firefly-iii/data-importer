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
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        $result = [
            'headers'                       => $this->convertBoolean($this->get('headers')),
            'delimiter'                     => $this->convertToString('delimiter'),
            'date'                          => $this->convertToString('date'),
            'default_account'               => $this->convertToInteger('default_account'),
            'rules'                         => $this->convertBoolean($this->get('rules')),
            'ignore_duplicate_lines'        => $this->convertBoolean($this->get('ignore_duplicate_lines')),
            'ignore_duplicate_transactions' => $this->convertBoolean($this->get('ignore_duplicate_transactions')),
            'skip_form'                     => $this->convertBoolean($this->get('skip_form')),
            'add_import_tag'                => $this->convertBoolean($this->get('add_import_tag')),
            'specifics'                     => [],
            'roles'                         => [],
            'mapping'                       => [],
            'do_mapping'                    => [],
            'flow'                          => $this->convertToString('flow'),
            'content_type'                  => $this->convertToString('content_type'),

            // camt options
            'grouped_transaction_handling'  => $this->convertToString('grouped_transaction_handling'),
            'use_entire_opposing_address'   => $this->convertBoolean('use_entire_opposing_address'),

            // duplicate detection:
            'duplicate_detection_method'    => $this->convertToString('duplicate_detection_method'),
            'unique_column_index'           => $this->convertToInteger('unique_column_index'),
            'unique_column_type'            => $this->convertToString('unique_column_type'),

            // spectre values:
            'connection'                    => $this->convertToString('connection'),
            'identifier'                    => $this->convertToString('identifier'),
            'ignore_spectre_categories'     => $this->convertBoolean($this->get('ignore_spectre_categories')),

            // nordigen:
            'nordigen_country'              => $this->convertToString('nordigen_country'),
            'nordigen_bank'                 => $this->convertToString('nordigen_bank'),
            'nordigen_max_days'             => $this->convertToString('nordigen_max_days'),
            'nordigen_requisitions'         => json_decode($this->convertToString('nordigen_requisitions'), true) ?? [],

            // nordigen + spectre
            'do_import'                     => $this->get('do_import') ?? [],
            'accounts'                      => $this->get('accounts') ?? [],
            'map_all_data'                  => $this->convertBoolean($this->get('map_all_data')),
            'date_range'                    => $this->convertToString('date_range'),
            'date_range_number'             => $this->convertToInteger('date_range_number'),
            'date_range_unit'               => $this->convertToString('date_range_unit'),
            'date_not_before'               => $this->getCarbonDate('date_not_before'),
            'date_not_after'                => $this->getCarbonDate('date_not_after'),

            // utf8 conversion
            'conversion'                    => $this->convertBoolean($this->get('conversion')),

        ];

        return $result;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        $rules = [
            'headers'                       => 'numeric|between:0,1',
            'delimiter'                     => 'in:comma,semicolon,tab',
            'date'                          => 'between:1,25',
            'default_account'               => 'required|numeric|min:1|max:100000',
            'rules'                         => 'numeric|between:0,1',
            'ignore_duplicate_lines'        => 'numeric|between:0,1',
            'ignore_duplicate_transactions' => 'numeric|between:0,1',
            'skip_form'                     => 'numeric|between:0,1',
            'add_import_tag'                => 'numeric|between:0,1',
            'ignore_spectre_categories'     => 'numeric|between:0,1',

            // camt options, if present:
            'use_entire_opposing_address'   => 'numeric|between:0,1',
            'grouped_transaction_handling'  => 'in:single,split,group',

            // duplicate detection:
            'duplicate_detection_method'    => 'in:cell,none,classic',
            'unique_column_index'           => 'numeric',
            'unique_column_type'            => sprintf('in:%s', join(',', array_keys(config('csv.unique_column_options')))),

            // conversion
            'conversion'                    => 'numeric|between:0,1',
        ];

        return $rules;
    }


    /**
     * Configure the validator instance with special rules for after the basic validation rules.
     *
     * @param Validator $validator
     *
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator) {
                // validate all account info
                $flow     = request()->cookie(Constants::FLOW_COOKIE);
                $data     = $validator->getData();
                $doImport = $data['do_import'] ?? [];
                if (0 === count($doImport) && 'file' !== $flow) {
                    $validator->errors()->add('do_import', 'You must select at least one account to import from.');
                }
            }
        );
    }
}
