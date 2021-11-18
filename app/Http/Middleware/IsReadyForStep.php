<?php
/*
 * IsReadyForStep.php
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Log;

/**
 * Trait IsReadyForStep
 */
trait IsReadyForStep
{
    /**
     * @param Request $request
     * @param string  $step
     * @return bool
     * @throws ImporterErrorException
     */
    protected function isReadyForStep(Request $request, string $step): bool
    {
        $flow = $request->cookie('flow');
        if (null === $flow) {
            Log::debug('isReadyForStep returns true because $flow is null');
            return true;
        }
        if ('csv' === $flow) {
            return $this->isReadyForCSVStep($request, $step);
        }
        if ('nordigen' === $flow) {
            die('TODO');
        }
        if ('spectre' === $flow) {
            die('TODO');
        }
    }

    /**
     * @param Request $request
     * @param string  $step
     * @return bool
     * @throws ImporterErrorException
     */
    private function isReadyForCSVStep(Request $request, string $step): bool
    {
        Log::debug(sprintf('isReadyForCSVStep("%s")', $step));
        switch ($step) {
            default:
                throw new ImporterErrorException(sprintf('isReadyForCSVStep: Cannot handle CSV step "%s"', $step));
            case 'upload-files':
                if (session()->has(Constants::HAS_UPLOAD) && true === session()->get(Constants::HAS_UPLOAD)) {
                    return false;
                }
                return true;
        }
    }

    /**
     * @param Request $request
     * @param string  $step
     * @return RedirectResponse|null
     * @throws ImporterErrorException
     */
    protected function redirectToCorrectStep(Request $request, string $step): ?RedirectResponse
    {
        $flow = $request->cookie('flow');
        if (null === $flow) {
            Log::debug('redirectToCorrectStep returns true because $flow is null');
            return null;
        }
        if ('csv' === $flow) {
            return $this->redirectToCorrectCSVStep($step);
        }
        if ('nordigen' === $flow) {
            die('TODO');
        }
        if ('spectre' === $flow) {
            die('TODO');
        }
        die('TODO');
    }

    /**
     * @param         $step
     * @return RedirectResponse
     * @throws ImporterErrorException
     */
    private function redirectToCorrectCSVStep($step): RedirectResponse
    {
        Log::debug(sprintf('redirectToCorrectCSVStep("%s")', $step));
        switch ($step) {
            default:
                throw new ImporterErrorException(sprintf('redirectToCorrectCSVStep: Cannot handle CSV step "%s"', $step));
            case 'upload-files':
                $route = route('004-configure.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));
                return redirect($route);
        }
    }

}
