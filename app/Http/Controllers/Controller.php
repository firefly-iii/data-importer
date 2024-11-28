<?php

/*
 * Controller.php
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

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Class Controller
 */
class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        // validate some env vars (skip over config)
        $accessToken = (string) env('FIREFLY_III_ACCESS_TOKEN', '');
        $clientId    = (string) env('FIREFLY_III_CLIENT_ID', '');
        $baseUrl     = (string) env('FIREFLY_III_URL', '');
        $vanityUrl   = (string) env('VANITY_URL', '');

        // access token AND client ID cannot be set together
        if ('' !== $accessToken && '' !== $clientId) {
            echo 'You can\'t set FIREFLY_III_ACCESS_TOKEN together with FIREFLY_III_CLIENT_ID. One must remain empty.';

            exit;
        }

        // if vanity URL is not empty, Firefly III url must also be set.
        if ('' !== $vanityUrl && '' === $baseUrl) {
            echo 'If you set VANITY_URL you must also set FIREFLY_III_URL';

            exit;
        }

        $path        = config('importer.upload_path');
        $writable    = is_dir($path) && is_writable($path);
        if (false === $writable) {
            echo sprintf('Make sure that directory "%s" exists and is writeable.', $path);

            exit;
        }

        app('view')->share('version', config('importer.version'));
    }
}
