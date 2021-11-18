<?php
/*
 * RolesComplete.php
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

namespace App\Http\Middleware;

use App\Services\Session\Constants;
use Closure;
use Illuminate\Http\Request;
use Log;

/**
 * Class RolesComplete
 */
class RolesComplete
{
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
        Log::debug('Now validate RolesComplete');
        $flow  = $request->cookie(Constants::FLOW_COOKIE);
        $route = route('006-mapping.index');
        if ('csv' !== $flow) {
            Log::debug(sprintf('"%s" flow cant do roles, so redirect to "%s" for mapping.', $flow, $route));
            return redirect($route);
        }
        Log::debug('Flow is CSV');
        if (session()->has(Constants::ROLES_COMPLETE_INDICATOR) && true === session()->get(Constants::ROLES_COMPLETE_INDICATOR)) {
            Log::debug('Session says roles are set so redirect to mapping.');
            return redirect($route);
        }
        Log::debug('Ready for roles!');
        return $next($request);
    }
}
