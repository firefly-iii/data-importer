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


use Carbon\Carbon;
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
        $count  = $this->integer('count');
        $result = [
            'count'          => $this->integer('count'),
            'configurations' => [],


        ];
        for ($i = 0; $i < $count; $i++) {
            $current = [
                'headers'                       => $this->getBoolFromArray($i, 'headers'),
                'delimiter'                     => $this->getStringFromArray($i, 'delimiter'),
                'date'                          => $this->getStringFromArray($i, 'date'),
                'default_account'               => $this->getIntegerFromArray($i, 'default_account'),
                'rules'                         => $this->getBoolFromArray($i, 'rules'),
                'ignore_duplicate_lines'        => $this->getBoolFromArray($i, 'ignore_duplicate_lines'),
                'ignore_duplicate_transactions' => $this->getBoolFromArray($i, 'ignore_duplicate_transactions'),
                'skip_form'                     => $this->getBoolFromArray($i, 'skip_form'),
                'add_import_tag'                => $this->getBoolFromArray($i, 'add_import_tag'),
                'flow'                          => $this->getStringFromArray($i, 'flow'),

                // duplicate detection:

                'duplicate_detection_method' => $this->getStringFromArray($i, 'duplicate_detection_method'),
                'unique_column_index'        => $this->getIntegerFromArray($i, 'unique_column_index'),
                'unique_column_type'         => $this->getStringFromArray($i, 'unique_column_type'),

                // spectre values:
                'connection'                 => $this->getStringFromArray($i, 'connection'),
                'identifier'                 => $this->getStringFromArray($i, 'identifier'),
                'ignore_spectre_categories'  => $this->getBoolFromArray($i, 'ignore_spectre_categories'),

                // nordigen:
                'nordigen_country'           => $this->getStringFromArray($i, 'nordigen_country'),
                'nordigen_bank'              => $this->getStringFromArray($i, 'nordigen_bank'),
                'nordigen_max_days'          => $this->getStringFromArray($i, 'nordigen_max_days'),
                'nordigen_requisitions'      => json_decode($this->getStringFromArray($i, 'nordigen_requisitions'), true) ?? [],

                // nordigen + spectre

                'do_import'         => $this->getArrayFromArray($i, 'do_import'),
                'accounts'          => $this->getArrayFromArray($i, 'accounts'),
                'map_all_data'      => $this->getBoolFromArray($i, 'map_all_data'),
                'date_range'        => $this->getStringFromArray($i, 'date_range'),
                'date_range_number' => $this->getIntegerFromArray($i, 'date_range_number'),
                'date_range_unit'   => $this->getStringFromArray($i, 'date_range_unit'),
                'date_not_before'   => $this->getDateFromArray($i, 'date_not_before'),
                'date_not_after'    => $this->getDateFromArray($i, 'date_not_after'),

                // utf8 conversion
                'conversion'        => $this->getBoolFromArray($i, 'conversion'),

                // next
                'specifics'         => [],
                'roles'             => [],
                'mapping'           => [],
                'do_mapping'        => [],
            ];


            $result['configurations'][] = $current;
        }

        return $result;
    }

    /**
     * @param int    $index
     * @param string $key
     * @return bool
     */
    private function getBoolFromArray(int $index, string $key): bool
    {
        $res = $this->get($key);
        if (is_array($res)) {
            return '1' === ($res[$index] ?? '0');
        }
        return false;
    }

    /**
     * @param int    $index
     * @param string $key
     * @return string
     */
    private function getStringFromArray(int $index, string $key): string
    {
        $res = $this->get($key);
        if (is_array($res)) {
            return (string) $res[$index];
        }
        return '';
    }

    /**
     * @param int    $index
     * @param string $key
     * @return int
     */
    private function getIntegerFromArray(int $index, string $key): int
    {
        $res = $this->get($key);
        if (is_array($res)) {
            return (int) $res[$index];
        }
        return 0;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        $rules = [
            'headers.*'                       => 'numeric|between:0,1',
            'delimiter.*'                     => 'in:comma,semicolon,tab',
            'date.*'                          => 'between:1,25',
            'default_account.*'               => 'required|numeric|min:1|max:100000',
            'rules.*'                         => 'numeric|between:0,1',
            'ignore_duplicate_lines.*'        => 'numeric|between:0,1',
            'ignore_duplicate_transactions.*' => 'numeric|between:0,1',
            'skip_form.*'                     => 'numeric|between:0,1',
            'add_import_tag.*'                => 'numeric|between:0,1',
            'ignore_spectre_categories.*'     => 'numeric|between:0,1',
            'duplicate_detection_method.*'    => 'in:cell,none,classic',
            'unique_column_index.*'           => 'numeric',
            'unique_column_type.*'            => sprintf('in:%s', join(',', array_keys(config('csv.unique_column_options')))),
            'conversion.*'                    => 'numeric|between:0,1',
            'flow.*'                          => 'in:file,nordigen,spectre',
            'map_all_data.*'                  => 'numeric|between:0,1',
            'date_range.*'                    => 'partial,all,range',
            'date_range_number.*'             => 'numeric',
            'date_range_unit.*'               => 'in:d,w,m,y',
            'date_not_before.*'               => 'date',
            'date_not_after.*'                => 'date',
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
                $data = $validator->getData();
                foreach ($data['flow'] as $index => $flow) {
                    $data     = $validator->getData();
                    $doImport = $data[$index]['do_import'] ?? [];
                    if (0 === count($doImport) && 'file' !== $flow) {
                        $validator->errors()->add(sprintf('do_import.%d', $index), 'You must select at least one account to import from.');
                    }
                }
            }
        );
    }

    /**
     * @param int    $index
     * @param string $key
     * @return array
     */
    private function getArrayFromArray(int $index, string $key): array
    {
        $res = $this->get($key);
        if (is_array($res)) {
            return $res[$index];
        }
        return [];
    }

    /**
     * @param int    $index
     * @param string $key
     * @return Carbon|null
     */
    private function getDateFromArray(int $index, string $key): ?Carbon
    {
        $res    = $this->get($key);
        $string = '';
        if (is_array($res)) {
            $string = (string) $res[$index];
        }
        if ('' === $string) {
            return null;
        }
        return Carbon::createFromFormat('Y-m-d', $string);
    }

}
