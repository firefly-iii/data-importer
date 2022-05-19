<?php
declare(strict_types=1);
/*
 * UploadRequest.php
 * Copyright (c) 2022 james@firefly-iii.org
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

namespace App\Http\Request;

use App\Services\Session\Constants;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Validator;

class UploadRequest extends Request
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
    public function rules(): array
    {
        return [];

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
                $importableFiles = $this->file('importable_file');
                $configFiles     = $this->file('config_file');
                $flow            = $this->cookie(Constants::FLOW_COOKIE);
                $importCount     = null === $importableFiles ? 0 : count($importableFiles);
                $configCount     = null === $configFiles ? 0 : count($configFiles);
                $oneConfig       = '1' === $this->get('one_config');

                // basic sanity checks:
                // 1. can only upload zero or one config
                if ($oneConfig && $configCount > 1) {
                    // return with error.
                    $validator->errors()->add('config_file', 'If you select that one configuration is enough, please do not upload multiple files.');
                    return;
                }

                // 2. if more than one file uploaded, nr of config files uploaded must be equal.
                if (!$oneConfig && $configCount !== 0 && $importCount !== $configCount && 'file' === $flow) {
                    // return with error.
                    $validator->errors()->add('importable_file', 'Please upload an equal number of importable files and configuration files.');
                    $validator->errors()->add('config_file', 'Please upload an equal number of importable files and configuration files.');
                    return;
                }
                // 3. if not uploaded anything, return:
                if (0 === $importCount && 'file' === $flow) {
                    // return with error.
                    $validator->errors()->add('importable_file', 'Please upload something.');
                    return;
                }
                // error for Spectre and Nordigen
                if ($configCount > 1 && 'file' !== $flow) {
                    $validator->errors()->add('config_file', 'This routine cannot handle more than 1 configuration.');
                }
            }
        );
    }
}
