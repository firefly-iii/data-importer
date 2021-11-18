<?php
/*
 * UploadControllerMiddleware.php
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

namespace App\Http\Middleware;

use App\Exceptions\ImporterErrorException;
use App\Services\Session\Constants;
use Illuminate\Http\Request;
use Closure;

/**
 * Class UploadControllerMiddleware
 */
class UploadControllerMiddleware
{
    use IsReadyForStep;

    /**
     * Check if the user has already uploaded files in this session. If so, continue to configuration.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     *
     */
    public function handle(Request $request, Closure $next)
    {
        $step = 'upload-files';
        $result = $this->isReadyForStep($request, $step);
        if(true === $result) {
            return $next($request);
        }

            $redirect = $this->redirectToCorrectStep($request, $step);
            if(null !== $redirect) {
                return $redirect;
            }
        throw new ImporterErrorException('Cannot handle upload controller');



    }

}
