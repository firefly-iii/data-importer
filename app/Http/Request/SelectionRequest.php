<?php
/*
 * SelectionRequest.php
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
 * Class SelectionRequest
 */
class SelectionRequest extends Request
{
    public function getAll(): array
    {
        $country = $this->get('country');
        $days    = $this->get('days');

        return [
            'country' => $country,
            'bank'    => $this->get(sprintf('bank_%s', $country)),
            'days'    => $days,
        ];
    }

    public function rules(): array
    {
        return [
            'country' => 'required|not_in:XX',
            'bank_*'  => 'required',
            'days'    => 'required|numeric|between:1,1000',
        ];
    }

    /**
     * Configure the validator instance with special rules for after the basic validation rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator): void {
                $data    = $validator->getData();
                $country = $data['country'];
                $key     = sprintf('bank_%s', $country);
                $value   = $data[$key] ?? 'XX';
                if ('XX' === $value) {
                    $validator->errors()->add('country', 'The selected bank is invalid.');
                }
            }
        );
    }
}
