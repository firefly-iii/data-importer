<?php
declare(strict_types=1);
/**
 * ConfigurationPostRequest.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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

namespace App\Http\Request;


use App\Services\CSV\Specifics\SpecificService;
use JsonException;
use Log;

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
        try {
            $roles = $this->get('roles') ?
                json_decode(base64_decode($this->get('roles')), true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException $e) {
            Log::warning(
                sprintf(
                    'Could not decode roles JSON "%s" ("%s") but this is not a problem.',
                    $this->get('roles'),
                    $e->getMessage()
                )
            );
            $roles = [];
        }
        try {
            $mapping = $this->get('mapping') ?
                json_decode(base64_decode($this->get('mapping')), true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException $e) {
            Log::warning(
                sprintf(
                    'Could not decode mapping JSON "%s" ("%s") but this is not a problem.',
                    $this->get('mapping'),
                    $e->getMessage()
                )
            );
            $mapping = [];
        }
        try {
            $doMapping = $this->get('do_mapping') ?
                json_decode(base64_decode($this->get('do_mapping')), true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException $e) {
            Log::warning(
                sprintf(
                    'Could not decode doMapping JSON "%s" ("%s") but this is not a problem.',
                    $this->get('do_mapping'),
                    $e->getMessage()
                )
            );
            $doMapping = [];
        }

        $result = [
            'headers'                       => $this->convertBoolean($this->get('headers')),
            'delimiter'                     => $this->string('delimiter'),
            'date'                          => $this->string('date'),
            'default_account'               => $this->integer('default_account'),
            'rules'                         => $this->convertBoolean($this->get('rules')),
            'ignore_duplicate_lines'        => $this->convertBoolean($this->get('ignore_duplicate_lines')),
            'ignore_duplicate_transactions' => $this->convertBoolean($this->get('ignore_duplicate_transactions')),
            'skip_form'                     => $this->convertBoolean($this->get('skip_form')),
            'add_import_tag'                => $this->convertBoolean($this->get('add_import_tag')),
            'specifics'                     => [],
            'roles'                         => $roles,
            'mapping'                       => $mapping,
            'do_mapping'                    => $doMapping,
            // duplicate detection:
            'duplicate_detection_method'    => $this->string('duplicate_detection_method'),
            'unique_column_index'           => $this->integer('unique_column_index'),
            'unique_column_type'            => $this->string('unique_column_type'),
        ];
        // rules for specifics:
        $specifics = SpecificService::getSpecifics();
        foreach (array_keys($specifics) as $specific) {
            $result['specifics'][$specific] = $this->convertBoolean($this->get(sprintf('specific_%s', $specific)));
        }

        return $result;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        $rules = [
            //'some_weird_field' => 'required',
            'headers'                       => 'numeric|between:0,1',
            'delimiter'                     => 'required|in:comma,semicolon,tab',
            'date'                          => 'required|between:1,15',
            'default_account'               => 'required|numeric|min:1|max:100000',
            'rules'                         => 'numeric|between:0,1',
            'ignore_duplicate_lines'        => 'numeric|between:0,1',
            'ignore_duplicate_transactions' => 'numeric|between:0,1',
            'skip_form'                     => 'numeric|between:0,1',
            'add_import_tag'                => 'numeric|between:0,1',

            // duplicate detection:
            'duplicate_detection_method'    => 'required|in:cell,none,classic',
            'unique_column_index'           => 'numeric',
            'unique_column_type'            => sprintf('required|in:%s', join(',', array_keys(config('csv_importer.unique_column_options')))),
        ];
        // rules for specifics:
        $specifics = SpecificService::getSpecifics();
        foreach (array_keys($specifics) as $specific) {
            $rules[sprintf('specific_%s', $specific)] = 'numeric|between:0,1';
        }

        return $rules;
    }
}
