<?php
/*
 * MappingComplete.php
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
use Log;
use PHPUnit\TextUI\XmlConfiguration\Constant;

/**
 * Class MappingComplete
 */
class MappingComplete
{
    /**
     * Check if the user has already set the mapping in this session. If so, continue to configuration.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     *
     */
    public function handle(Request $request, Closure $next)
    {
        Log::debug('Now in MappingComplete');
        $flow = $request->cookie(Constants::FLOW_COOKIE);
        Log::debug(sprintf('Flow is "%s"', $flow));
        if ('csv' === $flow) {
            $route = route('007-convert.index');
            if (session()->has(Constants::MAPPING_COMPLETE_INDICATOR) && true === session()->get(Constants::MAPPING_COMPLETE_INDICATOR)) {
                Log::debug(sprintf('Mapping is complete, redirect to "%s" for conversion.', $route));
                return redirect($route);
            }
            Log::debug('Mapping is not yet complete for CSV, continue.');
        }
        if ('csv' !== $flow) {
            // ready to submit
            if (session()->has(Constants::MAPPING_COMPLETE_INDICATOR) && true === session()->get(Constants::MAPPING_COMPLETE_INDICATOR)) {
                $route = route('008-submit.index');
                Log::debug(sprintf('Mapping is complete, will redirect to "%s" for submit of data.', $route));
            }

            // ready for mapping
            if (!session()->has(Constants::MAPPING_COMPLETE_INDICATOR) &&
                session()->has(Constants::CONVERSION_COMPLETE_INDICATOR) && true === session()->get(Constants::CONVERSION_COMPLETE_INDICATOR)
            ) {
                Log::debug(sprintf('No mapping complete indicator for "%s", but conversion is complete, ready for mapping!', $flow));
                return $next($request);
            }
            // not yet ready for mapping, first do conversion:
            if (!session()->has(Constants::MAPPING_COMPLETE_INDICATOR) && !session()->has(Constants::CONVERSION_COMPLETE_INDICATOR)
            ) {
                $route = route('007-convert.index');
                Log::debug(sprintf('No mapping complete indicator + no conversion complete for "%s", redirect to "%s" for conversion first.', $flow, $route));
                return redirect($route);
            }
        }
        throw new ImporterErrorException('Should not be here in mapping.');
    }
}
