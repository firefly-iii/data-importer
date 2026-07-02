<?php

/*
 * AutoImportRequest.php
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

use Illuminate\Support\Facades\Log;

final class AutoImportRequest extends Request
{
    /**
     * Verify the request: the feature must be enabled and the secret must match.
     * The response is a plain 403 on purpose; details only go to the log.
     */
    public function authorize(): bool
    {
        if (false === config('importer.can_post_autoimport')) {
            Log::warning('Denied autoimport request: CAN_POST_AUTOIMPORT is not enabled.');

            return false;
        }

        $secret       = (string) ($this->input('secret') ?? '');
        $systemSecret = (string) config('importer.auto_import_secret');
        if ('' === $systemSecret || strlen($systemSecret) < 16) {
            Log::warning('Denied autoimport request: AUTO_IMPORT_SECRET is not set or shorter than 16 characters.');

            return false;
        }
        if ('' === $secret || !hash_equals($systemSecret, $secret)) {
            Log::warning('Denied autoimport request: submitted secret does not match AUTO_IMPORT_SECRET.');

            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return ['directory' => 'string', 'secret' => 'required|string'];
    }
}
