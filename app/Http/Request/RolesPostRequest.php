<?php
/*
 * RolesPostRequest.php
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


use Illuminate\Validation\Validator;

/**
 * Class RolesPostRequest
 */
class RolesPostRequest extends Request
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
        $count               = $this->integer('count');
        $singleConfiguration = $this->boolean('single_configuration');
        $result              = [
            'count'          => $this->integer('count'),
            'configurations' => [],
        ];

        for ($i = 0; $i < $count; $i++) {
            $index = $i;
            if ($singleConfiguration) {
                $index = 0;
            }
            $current = [
                'roles'      => $this->getArrayFromArray($index, 'roles'),
                'do_mapping' => $this->getArrayFromArray($index, 'do_mapping'),
            ];

            foreach (array_keys($current['roles']) as $ii) {
                $current['do_mapping'][$ii] = $this->convertBoolean($current['do_mapping'][$ii] ?? 'false');
            }
            $result['configurations'][] = $current;
        }
        return $result;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        $keys = implode(',', array_keys(config('csv.import_roles')));

        return [
            'roles.*.*'      => sprintf('required|in:%s', $keys),
            'do_mapping.*.*' => 'numeric|between:0,1',
        ];
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
                $this->validateAmountRole($validator);
            }
        );
    }

    /**
     * @param Validator $validator
     */
    protected function validateAmountRole(Validator $validator): void
    {
        $data  = $validator->getData();
        $roles = $data['roles'] ?? [];

        foreach ($roles as $index => $set) {
            $count = 0;
            foreach ($set as $role) {
                if (in_array($role, ['amount', 'amount_negated', 'amount_debit', 'amount_credit'], true)) {
                    $count++;
                }
            }
            if (0 === $count) {
                $validator->errors()->add(sprintf('roles.%d.0', $index), 'The import will fail if no column is assigned an "Amount"-role.');
            }
        }
    }
}
