<?php
declare(strict_types=1);
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
use Closure;
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
     * @param Closure $next
     *
     * @return mixed
     * @throws ImporterErrorException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $result = $this->isReadyForStep($request);
        if (true === $result) {
            return $next($request);
        }

        $redirect = $this->redirectToCorrectStep($request);
        if (null !== $redirect) {
            return $redirect;
        }
        throw new ImporterErrorException(sprintf('Cannot handle middleware: %s', self::STEP));
    }

    /**
     * @param Request $request
     * @return bool
     * @throws ImporterErrorException
     */
    protected function isReadyForStep(Request $request): bool
    {
        $flow = $request->cookie('flow');
        if (null === $flow) {
            Log::debug('isReadyForStep returns true because $flow is null');
            return true;
        }
        if ('csv' === $flow) {
            return $this->isReadyForCSVStep();
        }
        if ('nordigen' === $flow) {
            return $this->isReadyForNordigenStep();
        }
        if ('spectre' === $flow) {
            die('TODO ' . __METHOD__);
        }
        return $this->isReadyForBasicStep();
    }

    /**
     * @return bool
     * @throws ImporterErrorException
     */
    private function isReadyForBasicStep(): bool
    {
        Log::debug(sprintf('isReadyForBasicStep("%s")', self::STEP));
        switch (self::STEP) {
            default:
                throw new ImporterErrorException(sprintf('isReadyForBasicStep: Cannot handle basic step "%s"', self::STEP));
            case 'service-validation':
                return true;
        }
    }

    /**
     * @return bool
     * @throws ImporterErrorException
     */
    private function isReadyForNordigenStep(): bool
    {
        Log::debug(sprintf('isReadyForNordigenStep("%s")', self::STEP));
        switch (self::STEP) {
            default:
                throw new ImporterErrorException(sprintf('isReadyForNordigenStep: Cannot handle Nordigen step "%s"', self::STEP));
            case 'authenticate':
            case 'service-validation':
                return true;
            case 'define-roles':
                return false;
            case 'upload-files':
                if (session()->has(Constants::HAS_UPLOAD) && true === session()->get(Constants::HAS_UPLOAD)) {
                    return false;
                }
                return true;
            case 'nordigen-selection':
                // must have upload, thats it
                if (session()->has(Constants::HAS_UPLOAD) && true === session()->get(Constants::HAS_UPLOAD)) {
                    return true;
                }
                return false;
            case 'map':
                if (session()->has(Constants::MAPPING_COMPLETE_INDICATOR) && true === session()->get(Constants::MAPPING_COMPLETE_INDICATOR)) {
                    return false;
                }
                return true;
            case 'nordigen-link':
                // must have upload, thats it
                if (session()->has(Constants::SELECTED_BANK_COUNTRY) && true === session()->get(Constants::SELECTED_BANK_COUNTRY)) {
                    return true;
                }
                return false;
            case 'conversion':
                // if/else is in reverse!
                if (session()->has(Constants::READY_FOR_CONVERSION) && true === session()->get(Constants::READY_FOR_CONVERSION)) {
                    return true;
                }
                // will probably never return false, but OK.
                return false;
            case 'configuration':
                if (session()->has(Constants::SELECTED_BANK_COUNTRY) && true === session()->get(Constants::SELECTED_BANK_COUNTRY)) {
                    return true;
                }
                return false;
        }
    }

    /**
     * @return bool
     * @throws ImporterErrorException
     */
    private function isReadyForCSVStep(): bool
    {
        Log::debug(sprintf('isReadyForCSVStep("%s")', self::STEP));
        switch (self::STEP) {
            default:
                throw new ImporterErrorException(sprintf('isReadyForCSVStep: Cannot handle CSV step "%s"', self::STEP));
            case 'service-validation':
                return true;
            case 'upload-files':
                if (session()->has(Constants::HAS_UPLOAD) && true === session()->get(Constants::HAS_UPLOAD)) {
                    return false;
                }
                return true;
            case 'authenticate':
                // for CSV this is always false.
                return false;
            case 'define-roles':
                if (session()->has(Constants::ROLES_COMPLETE_INDICATOR) && true === session()->get(Constants::ROLES_COMPLETE_INDICATOR)) {
                    return false;
                }
                return true;
            case 'configuration':
                if (session()->has(Constants::CONFIG_COMPLETE_INDICATOR) && true === session()->get(Constants::CONFIG_COMPLETE_INDICATOR)) {
                    return false;
                }
                return true;
            case 'map':
                if (session()->has(Constants::MAPPING_COMPLETE_INDICATOR) && true === session()->get(Constants::MAPPING_COMPLETE_INDICATOR)) {
                    return false;
                }
                return true;
            case 'conversion':
                // if/else is in reverse!
                if (session()->has(Constants::READY_FOR_CONVERSION) && true === session()->get(Constants::READY_FOR_CONVERSION)) {
                    return true;
                }
                // will probably never return false, but OK.
                return false;
            case 'submit':
                // if/else is in reverse!
                if (session()->has(Constants::CONVERSION_COMPLETE_INDICATOR) && true === session()->get(Constants::CONVERSION_COMPLETE_INDICATOR)) {
                    return true;
                }
                return false;
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse|null
     * @throws ImporterErrorException
     */
    protected function redirectToCorrectStep(Request $request): ?RedirectResponse
    {
        $flow = $request->cookie(Constants::FLOW_COOKIE);
        if (null === $flow) {
            Log::debug('redirectToCorrectStep returns true because $flow is null');
            return null;
        }
        if ('csv' === $flow) {
            return $this->redirectToCorrectCSVStep();
        }
        if ('nordigen' === $flow) {
            return $this->redirectToCorrectNordigenStep();
        }
        if ('spectre' === $flow) {
            die('TODO ' . __METHOD__);
        }
        return $this->redirectToBasicStep();
    }

    /**
     * @return RedirectResponse
     * @throws ImporterErrorException
     */
    private function redirectToBasicStep(): RedirectResponse
    {
        Log::debug(sprintf('redirectToBasicStep("%s")', self::STEP));
        switch (self::STEP) {
            default:
                throw new ImporterErrorException(sprintf('redirectToBasicStep: Cannot handle basic step "%s"', self::STEP));
        }

    }
    /**
     * @return RedirectResponse
     * @throws ImporterErrorException
     */
    private function redirectToCorrectNordigenStep(): RedirectResponse
    {
        Log::debug(sprintf('redirectToCorrectNordigenStep("%s")', self::STEP));
        switch (self::STEP) {
            default:
                throw new ImporterErrorException(sprintf('redirectToCorrectNordigenStep: Cannot handle Nordigen step "%s"', self::STEP));
            case 'nordigen-selection':
                // back to upload
                $route = route('003-upload.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));
                return redirect($route);
            case 'nordigen-link':
                // back to selection
                $route = route('009-selection.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));
                return redirect($route);
            case 'define-roles':
                $route = route('006-mapping.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));
                return redirect($route);
        }
    }

    /**
     * @return RedirectResponse
     * @throws ImporterErrorException
     */
    private function redirectToCorrectCSVStep(): RedirectResponse
    {
        Log::debug(sprintf('redirectToCorrectCSVStep("%s")', self::STEP));
        switch (self::STEP) {
            default:
                throw new ImporterErrorException(sprintf('redirectToCorrectCSVStep: Cannot handle CSV step "%s"', self::STEP));
            case 'upload-files':
                $route = route('004-configure.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));
                return redirect($route);
            case 'define-roles':
                $route = route('006-mapping.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));
                return redirect($route);
            case 'configuration':
                $route = route('005-roles.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));
                return redirect($route);
            case 'map':
                $route = route('007-convert.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));
                return redirect($route);
            case 'conversion':
                // redirect to mapping
                $route = route('006-mapping.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));
                return redirect($route);
            case 'authenticate':
                $route = route('003-upload.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));
                return redirect($route);
        }
    }

}
