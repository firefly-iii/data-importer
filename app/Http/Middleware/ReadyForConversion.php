<?php
/*
 * ReadyForImport.php
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

use App\Exceptions\ImporterErrorException;
use App\Services\Session\Constants;
use Closure;
use Illuminate\Http\Request;

/**
 * Class ReadyForConversion
 */
class ReadyForConversion
{
    /**
     * Check if the user has already set the mapping in this session. If so, continue to configuration.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     *
     * @throws ImporterErrorException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (session()->has(Constants::MAPPING_COMPLETE_INDICATOR) && true === session()->get(Constants::MAPPING_COMPLETE_INDICATOR)) {
            return $next($request);
        }

        throw new ImporterErrorException('Conversion is not yet ready.');
    }
}
