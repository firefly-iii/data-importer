<?php

/*
 * ConversionControllerMiddleware.php
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class ConversionControllerMiddleware
 */
class ConversionControllerMiddleware
{
    use IsReadyForStep;

    protected const string STEP = 'conversion';

    protected function isReadyForStep(Request $request): bool
    {
        $flow = $request->cookie(Constants::FLOW_COOKIE);

        // Call trait logic directly since we can't use parent:: with traits
        if (null === $flow) {
            Log::debug('isReadyForStep returns true because $flow is null');

            return true;
        }

        if ('file' === $flow) {
            $result = $this->isReadyForFileStep();
            Log::debug(sprintf('isReadyForFileStep: Return %s', var_export($result, true)));

            return $result;
        }
        if ('nordigen' === $flow) {
            return $this->isReadyForNordigenStep();
        }
        if ('spectre' === $flow) {
            return $this->isReadyForSpectreStep();
        }
        if ('simplefin' === $flow) {
            return $this->isReadyForSimpleFINStep();
        }
        if ('lunchflow' === $flow) {
            return $this->isReadyForLunchFlowStep();
        }

        return $this->isReadyForBasicStep();
    }
}
