<?php
/*
 * PostAccountRequest.php
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

namespace App\Services\SimpleFIN\Request;

use GrumpyDictator\FFIIIApiSupport\Request\Request;
use GrumpyDictator\FFIIIApiSupport\Response\Response;
use GrumpyDictator\FFIIIApiSupport\Response\ValidationErrorResponse;
use App\Services\SimpleFIN\Response\PostAccountResponse;

/**
 * Class PostAccountRequest
 * POST an account to Firefly III.
 */
class PostAccountRequest extends Request
{
    /**
     * PostAccountRequest constructor.
     */
    public function __construct(string $url, string $token)
    {
        $this->setBase($url);
        $this->setToken($token);
        $this->setUri('accounts');
    }

    public function get(): Response
    {
        // TODO: Implement get() method.
    }

    public function post(): Response
    {
        $data = $this->authenticatedPost();

        // found error in response:
        if (array_key_exists('errors', $data) && is_array($data['errors'])) {
            return new ValidationErrorResponse($data['errors']);
        }

        // should be impossible to get here (see previous code) but still check.
        if (!array_key_exists('data', $data)) {
            // return with error array:
            if (array_key_exists('errors', $data) && is_array($data['errors'])) {
                return new ValidationErrorResponse($data['errors']);
            }
            // no data array and no error info, that's weird!
            $info = ['unknown_field' => [sprintf('Unknown error: %s', json_encode($data, 0, 16))]];

            return new ValidationErrorResponse($info);
        }

        return new PostAccountResponse($data['data'] ?? []);
    }

    public function put(): Response
    {
        // TODO: Implement put() method.
    }
}
