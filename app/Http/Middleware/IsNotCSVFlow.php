<?php
/*
 * IsNotCSVFlow.php
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

use App\Services\Session\Constants;
use Closure;
use Illuminate\Http\Request;
use Log;

/**
 * Class IsNotCSVFlow
 */
class IsNotCSVFlow
{
    /**
     * Check if the user is NOT doing CSV.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     *
     */
    public function handle(Request $request, Closure $next)
    {
        $flow = $request->cookie(Constants::FLOW_COOKIE);
        Log::debug(sprintf('Now in IsNotCSVFlow with flow "%s"', $flow));
        if ('csv' === $flow) {
            $route = route('003-upload.index');
            Log::debug(sprintf('Flow is "%s", user will be redirected to %s', $flow, $route));
            return redirect($route);
        }
        Log::debug(sprintf('Flow is "%s", user can stay.', $flow));

        return $next($request);
    }
}
