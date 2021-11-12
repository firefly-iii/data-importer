<?php
declare(strict_types=1);


/**
 * Controller.php
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

namespace App\Http\Controllers;

use App\Exceptions\ImporterHttpException;
use App\Services\Nordigen\TokenManager;
use App\Services\Spectre\Request\ListCustomersRequest;
use App\Services\Spectre\Response\ErrorResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Log;

/**
 * Class Controller
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $path     = config('importer.upload_path');
        $writable = is_dir($path) && is_writable($path);
        if (false === $writable) {
            echo sprintf('Make sure that directory "%s" exists and is writeable.', $path);
            exit;
        }

        app('view')->share('version', config('importer.version'));
    }

    /**
     * @return string|null
     */
    protected function verifyNordigen(): ?string
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        // is there a valid access and refresh token?
        if (TokenManager::hasValidRefreshToken() && TokenManager::hasValidAccessToken()) {
            return null;
        }

        if (TokenManager::hasExpiredRefreshToken()) {
            // refresh!
            TokenManager::getFreshAccessToken();
        }

        // get complete set!
        try {
            TokenManager::getNewTokenSet();
        } catch (ImporterHttpException $e) {
            return $e->getMessage();
        }
        return null;
    }

    /**
     * @return string|null
     */
    protected function verifySpectre(): ?string
    {
        $url     = config('spectre.url');
        $appId   = config('spectre.app_id');
        $secret  = config('spectre.secret');
        $request = new ListCustomersRequest($url, $appId, $secret);

        $request->setTimeOut(config('importer.connection.timeout'));

        try {
            $response = $request->get();
        } catch (ImporterHttpException $e) {
            return $e->getMessage();
        }
        if ($response instanceof ErrorResponse) {
            return sprintf('%s: %s', $response->class, $response->message);
        }

        return null;
    }

}
