<?php

/*
 * RolesPostRequest.php
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

use Illuminate\Contracts\Validation\Validator;

/**
 * Class RolesPostRequest
 */
class RolesPostRequest extends Request
{
    /**
     * Verify the request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function getAllForFile(): array
    {
        $data = [
            'roles'      => $this->get('roles') ?? [],
            'do_mapping' => $this->get('do_mapping') ?? [],
        ];
        foreach (array_keys($data['roles']) as $index) {
            $data['do_mapping'][$index] = $this->convertBoolean($data['do_mapping'][$index] ?? 'false');
        }

        return $data;
    }

    public function rules(): array
    {
        $keys = implode(',', array_keys(config('csv.import_roles')));

        return [
            'roles.*'      => sprintf('required|in:%s', $keys),
            'do_mapping.*' => 'numeric|between:0,1',
        ];
    }

    /**
     * Configure the validator instance with special rules for after the basic validation rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator): void {
                // validate all account info
                $this->validateAmountRole($validator);
            }
        );
    }

    protected function validateAmountRole(Validator $validator): void
    {
        $data                 = $validator->getData(); // @phpstan-ignore-line
        $roles                = $data['roles'] ?? [];
        $ignoreWarnings = '1' === ($data['ignore_warnings'] ?? false);
        $count                = 0;
        $countDates           = 0;
        $countTransactionDate = 0;
        $hasDescription = false;
        $total = count($roles);
        $ignored = 0;
        foreach ($roles as $role) {
            if('_ignore' === $role) {
                $ignored++;
                continue;
            }
            if (in_array($role, ['amount', 'amount_negated', 'amount_debit', 'amount_credit'], true)) {
                ++$count;
            }
            if (in_array($role, ['date_interest', 'date_book', 'date_process', 'date_due','date_payment','date_invoice'], true)) {
                ++$countDates;
            }
            if('date_transaction' === $role) {
                $countTransactionDate++;
            }
            if('description' === $role) {
                $hasDescription = true;
            }
        }
        if($ignored === $total) {
            $validator->errors()->add('roles.0', 'You must select some roles to continue.');
        }
        if(!$hasDescription && !$ignoreWarnings) {
            $validator->errors()->add('roles.0', 'Without a column with the role "Description", your transactions will be imported with the description "(no description)".');
        }
        if(0 === $countTransactionDate && $countDates > 0 && !$ignoreWarnings) {
            $validator->errors()->add('roles.0', 'You selected a date, but not "Date (primary transaction date)". If you do not give any column the "Date (primary transaction date)" role, all your transactions will be imported with today\'s date.');
        }
        if(0 === $countTransactionDate && 0 === $countDates && !$ignoreWarnings) {
            $validator->errors()->add('roles.0', 'You have not set a column to the role of "Date (primary transaction date)". All your transactions will be imported with today\'s date.');
        }
        if (0 === $count) {
            $validator->errors()->add('roles.0', 'The import will fail if no column is assigned an "Amount"-role.');
        }
    }
}
