<?php

/*
 * IsReadyForStep.php
 * Copyright (c) 2025 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Trait IsReadyForStep
 */
trait IsReadyForStep
{
    // in these flows and step combinations, the answer is always the same:
    private array $staticAnswers = [
        'file'      => [
            'authenticate' => false,
        ],
        'nordigen'  => [
            'authenticate' => true,
            'define-roles' => false,
        ],
        'lunchflow' => [
            'authenticate' => true,
            'define-roles' => false,
        ],
        'spectre'   => [
            'authenticate' => true,
            'define-roles' => false,
        ],
        'simplefin' => [
            'authenticate' => false,
            'define-roles' => false,
        ],
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $flow     = $request->cookie(Constants::FLOW_COOKIE);
        $result   = $this->isReadyForStep($request);
        if (true === $result) {
            return $next($request);
        }

        $redirect = $this->redirectToCorrectStep($request);
        if (null !== $redirect) {
            return $redirect;
        }

        throw new ImporterErrorException(sprintf('Cannot handle step "%s" for flow "%s"', self::STEP, $flow));
    }

    protected function isReadyForStep(Request $request): bool
    {
        $flow = $request->cookie(Constants::FLOW_COOKIE);
        if (null === $flow) {
            Log::debug(sprintf('isReadyForStep(flow: %s, step: %s) returns true because $flow is null', $flow, self::STEP));

            return true;
        }

        // we are always ready for some steps:
        if ('service-validation' === self::STEP) {
            Log::debug(sprintf('isReadyForStep(flow: %s, step: %s) returns true because the step is "service-validation"', $flow, self::STEP));

            return true;
        }

        // this step is the same for everybody:
        if ('upload-files' === self::STEP) {
            // ready for this step if NO uploads.
            $res = !(session()->has(Constants::HAS_UPLOAD) && true === session()->get(Constants::HAS_UPLOAD));
            Log::debug(sprintf('isReadyForStep(flow: %s, step: %s) returns %s.', $flow, self::STEP, var_export($res, true)));

            return $res;
        }

        // static answer for some steps and roles.
        if (array_key_exists($flow, $this->staticAnswers) && array_key_exists(self::STEP, $this->staticAnswers[$flow])) {
            Log::debug(sprintf('Return %s because there is a static answer for for flow "%s" and step "%s".', var_export($this->staticAnswers[$flow][self::STEP], true), $flow, self::STEP));

            return $this->staticAnswers[$flow][self::STEP];
        }

        // for "define-roles", answer is false unless the flow allows you to define roles.
        if ('define-roles' === self::STEP) {
            $res = $this->isReadyToDefineRoles($flow);
            Log::debug(sprintf('isReadyForStep(flow: %s, step: %s) returns %s.', $flow, self::STEP, var_export($res, true)));

            return $res;
        }
        if ('configuration' === self::STEP) {
            $res = $this->isReadyForConfiguration();
            Log::debug(sprintf('isReadyForStep(flow: %s, step: %s) returns %s.', $flow, self::STEP, var_export($res, true)));

            return $res;
        }
        if ('map' === self::STEP) {
            $res = $this->isReadyForMapping($flow);
            Log::debug(sprintf('isReadyForStep(flow: %s, step: %s) returns %s.', $flow, self::STEP, var_export($res, true)));

            return $res;
        }
        if ('conversion' === self::STEP) {
            $res = $this->isReadyForConversion();
            Log::debug(sprintf('isReadyForStep(flow: %s, step: %s) returns %s.', $flow, self::STEP, var_export($res, true)));

            return $res;
        }
        if ('submit' === self::STEP) {
            $res = $this->isReadyForSubmission();
            Log::debug(sprintf('isReadyForStep(flow: %s, step: %s) returns %s.', $flow, self::STEP, var_export($res, true)));

            return $res;
        }

        // nordigen (gocardless) and SimpleFIN have their own special steps.
        if ('nordigen' === $flow) {
            return $this->isReadyForNordigenStep();
        }
        if ('spectre' === $flow) {
            return $this->isReadyForSpectreStep();
        }

        throw new ImporterErrorException(sprintf('Cannot handle step "%s" in flow "%s"', self::STEP, $flow)); // @phpstan-ignore-line
    }

    private function isReadyForSubmission(): bool
    {
        if (session()->has(Constants::CONVERSION_COMPLETE_INDICATOR) && true === session()->get(Constants::CONVERSION_COMPLETE_INDICATOR)) {
            return true;
        }

        return false;
    }

    private function isReadyForConversion(): bool
    {
        if (session()->has(Constants::READY_FOR_SUBMISSION) && true === session()->get(Constants::READY_FOR_SUBMISSION)) {
            Log::debug('Return false, already ready for submission.');

            return false;
        }
        // if/else is in reverse!
        if (session()->has(Constants::READY_FOR_CONVERSION) && true === session()->get(Constants::READY_FOR_CONVERSION)) {
            Log::debug('Return true, ready for conversion.');

            return true;
        }
        Log::debug('Return false, fallback.');

        // will probably never return false, but OK.
        return false;
    }

    /**
     * For mapping, the order in which this happens is different per flow. That makes this function a little complex.
     */
    private function isReadyForMapping(string $flow): bool
    {
        if (session()->has(Constants::MAPPING_COMPLETE_INDICATOR) && true === session()->get(Constants::MAPPING_COMPLETE_INDICATOR)) {
            return false;
        }

        if ('nordigen' === $flow || 'spectre' === $flow || 'lunchflow' === $flow) {

            // conversion complete?
            if (session()->has(Constants::CONVERSION_COMPLETE_INDICATOR) && true === session()->get(Constants::CONVERSION_COMPLETE_INDICATOR)) {
                Log::debug(sprintf('%s: Return true, ready for step [4].', $flow));

                return true;
            }

            // must already have the conversion, or not ready for this step:
            if (session()->has(Constants::READY_FOR_CONVERSION) && true === session()->get(Constants::READY_FOR_CONVERSION)) {
                Log::debug(sprintf('%s: return false, not yet ready for step [2].', $flow));

                return false;
            }
        }
        // otherwise return false.
        Log::debug('Return true, ready for step [3].');

        return true;
    }

    private function isReadyForConfiguration(): bool
    {
        if (session()->has(Constants::CONFIG_COMPLETE_INDICATOR) && true === session()->get(Constants::CONFIG_COMPLETE_INDICATOR)) {
            // if a key is present, we are not ready for this step (aka ready for the next one).
            Log::debug('isReadyForConfiguration: return false because configuration is already complete.');

            return false;
        }
        // by definition, not ready for this step. Need to have all counts:
        $count = 0;
        if (session()->has(Constants::SELECTED_BANK_COUNTRY) && true === session()->get(Constants::SELECTED_BANK_COUNTRY)) {
            // becomes true when user has selected bank + country.
            ++$count; // 1
            Log::debug(sprintf('isReadyForConfiguration: user has selected bank and country, count is now %d.', $count));
        }
        if (session()->has(Constants::SIMPLEFIN_ACCOUNTS_DATA) && true === session()->get(Constants::SIMPLEFIN_ACCOUNTS_DATA)) {
            // becomes true when user has selected bank + country.
            ++$count; // 2
            Log::debug(sprintf('isReadyForConfiguration: user has simplefin accounts data, count is now %d.', $count));
        }
        if (session()->has(Constants::HAS_UPLOAD) && true === session()->get(Constants::HAS_UPLOAD)) {
            // has upload is always true after the uploadcontroller.
            ++$count; // 3
            Log::debug(sprintf('isReadyForConfiguration: user has upload, count is now %d.', $count));
        }
        Log::debug(sprintf('isReadyForConfiguration: final count is now %d.', $count));

        return 3 === $count;
    }

    private function isReadyToDefineRoles(string $flow): bool
    {
        if (true === config(sprintf('importer.can_define_roles.%s', $flow), false)) {
            if (session()->has(Constants::ROLES_COMPLETE_INDICATOR) && true === session()->get(Constants::ROLES_COMPLETE_INDICATOR)) {
                return false;
            }

            return true;
        }

        return false;
    }

    private function isReadyForNordigenStep(): bool
    {
        // Log::debug(sprintf('isReadyForNordigenStep("%s")', self::STEP));
        switch (self::STEP) {
            default:
                throw new ImporterErrorException(sprintf('isReadyForNordigenStep: Cannot handle Nordigen step "%s"', self::STEP));

            case 'nordigen-selection':
                // must have upload, that's it
                if (session()->has(Constants::HAS_UPLOAD) && true === session()->get(Constants::HAS_UPLOAD)) {
                    return true;
                }

                return false;

            case 'nordigen-link':
                // must have upload, thats it
                if (session()->has(Constants::SELECTED_BANK_COUNTRY) && true === session()->get(Constants::SELECTED_BANK_COUNTRY)) {
                    return true;
                }

                return false;
        }
    }

    private function isReadyForSpectreStep(): bool
    {
        Log::debug(sprintf('isReadyForSpectreStep("%s")', self::STEP));

        switch (self::STEP) {
            default:
                throw new ImporterErrorException(sprintf('isReadyForSpectreStep: Cannot handle Spectre step "%s"', self::STEP));

            case 'select-connection':
                if (session()->has(Constants::HAS_UPLOAD) && true === session()->get(Constants::HAS_UPLOAD)) {
                    return true;
                }

                return false;
        }
    }

    protected function redirectToCorrectStep(Request $request): ?RedirectResponse
    {
        $flow = $request->cookie(Constants::FLOW_COOKIE);
        if (null === $flow) {
            Log::debug('redirectToCorrectStep returns NULL because $flow is null');

            return null;
        }
        if ('file' === $flow) {
            return $this->redirectToCorrectFileStep();
        }
        if ('nordigen' === $flow) {
            return $this->redirectToCorrectNordigenStep();
        }
        if ('spectre' === $flow) {
            return $this->redirectToCorrectSpectreStep();
        }
        if ('simplefin' === $flow) {
            return $this->redirectToCorrectSimpleFINStep();
        }
        if ('lunchflow' === $flow) {
            return $this->redirectToCorrectLunchFlowStep();
        }

        return null;
    }

    private function redirectToCorrectLunchFlowStep()
    {
        switch (self::STEP) {
            default:
                throw new ImporterErrorException(sprintf('redirectToCorrectLunchFlowStep: Cannot handle file step "%s"', self::STEP));

            case 'define-roles':
                $route = route('006-mapping.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);

            case 'map':
                // if no conversion yet, go there first
                // must already have the conversion, or not ready for this step:
                if (session()->has(Constants::READY_FOR_CONVERSION) && true === session()->get(Constants::READY_FOR_CONVERSION)) {
                    Log::debug('Is ready for conversion, so send to conversion.');
                    $route = route('007-convert.index');
                    Log::debug(sprintf('Return redirect to "%s"', $route));

                    return redirect($route);
                }
                Log::debug('Is ready for submit.');
                // otherwise go to import right away
                $route = route('008-submit.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);

            case 'conversion':
                if (session()->has(Constants::READY_FOR_SUBMISSION) && true === session()->get(Constants::READY_FOR_SUBMISSION)) {
                    $route = route('008-submit.index');
                    Log::debug(sprintf('Return redirect to "%s"', $route));

                    return redirect($route);
                }

                throw new ImporterErrorException(sprintf('redirectToCorrectLunchFlowStep: Cannot handle Lunch Flow step "%s" [1]', self::STEP));

        }
    }

    /**
     * @throws ImporterErrorException
     */
    private function redirectToCorrectFileStep(): RedirectResponse
    {
        Log::debug(sprintf('redirectToCorrectFileStep("%s")', self::STEP));

        switch (self::STEP) {
            default:
                throw new ImporterErrorException(sprintf('redirectToCorrectFileStep: Cannot handle file step "%s"', self::STEP));

            case 'authenticate':
                $route             = route('003-upload.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);

            case 'upload-files':
                $route             = route('004-configure.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);

            case 'define-roles':
                $route             = route('006-mapping.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);

            case 'configuration':
                $route             = route('005-roles.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);

            case 'map':
                $route             = route('007-convert.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);

            case 'conversion':
                // Check authoritative session flow before defaulting to file-based redirection
                $authoritativeFlow = null;
                $sessionConfig     = session()->get(Constants::CONFIGURATION);
                if (is_array($sessionConfig) && array_key_exists('flow', $sessionConfig) && null !== $sessionConfig['flow']) {
                    $authoritativeFlow = $sessionConfig['flow'];
                }

                // If authoritative flow is SimpleFIN, redirect to configure instead of mapping
                if ('simplefin' === $authoritativeFlow) {
                    $route = route('004-configure.index');

                    return redirect($route);
                }

                // Default file-based behavior: redirect to mapping
                $route             = route('006-mapping.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);

            case 'submit':
                // return back to conversion:
                $route             = route('007-convert.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);
        }
    }

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

            case 'configuration':
            case 'upload-files':
            case 'nordigen-link':
                // assume files are uploaded, go to step 11 (connection selection)
                // back to selection
                $route = route('009-selection.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);

            case 'define-roles':
                // will always push to mapping, and mapping will send the user back to
                // the right step.
                $route = route('006-mapping.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);

            case 'map':
                // if no conversion yet, go there first
                // must already have the conversion, or not ready for this step:
                if (session()->has(Constants::READY_FOR_CONVERSION) && true === session()->get(Constants::READY_FOR_CONVERSION)) {
                    Log::debug('Is ready for conversion, so send to conversion.');
                    $route = route('007-convert.index');
                    Log::debug(sprintf('Return redirect to "%s"', $route));

                    return redirect($route);
                }
                Log::debug('Is ready for submit.');
                // otherwise go to import right away
                $route = route('008-submit.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);

            case 'conversion':
                if (session()->has(Constants::READY_FOR_SUBMISSION) && true === session()->get(Constants::READY_FOR_SUBMISSION)) {
                    $route = route('008-submit.index');
                    Log::debug(sprintf('Return redirect to "%s"', $route));

                    return redirect($route);
                }

                throw new ImporterErrorException(sprintf('redirectToCorrectNordigenStep: Cannot handle Nordigen step "%s" [1]', self::STEP));

            case 'submit':
                $route = route('007-convert.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);
        }
    }

    private function redirectToCorrectSpectreStep(): RedirectResponse
    {
        Log::debug(sprintf('redirectToCorrectSpectreStep("%s")', self::STEP));

        switch (self::STEP) {
            default:
                throw new ImporterErrorException(sprintf('redirectToCorrectSpectreStep: Cannot handle basic step "%s"', self::STEP));

            case 'upload-files':
                // assume files are uploaded, go to step 11 (connection selection)
                // back to selection
                $route = route('011-connections.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);

            case 'define-roles':
                // will always push to mapping, and mapping will send them to
                // the right step.
                $route = route('006-mapping.index');
                Log::debug(sprintf('Return redirect to "%s"', $route));

                return redirect($route);

            case 'map':
                // if no conversion yet, go there first
                // must already have the conversion, or not ready for this step:
                if (session()->has(Constants::READY_FOR_CONVERSION) && true === session()->get(Constants::READY_FOR_CONVERSION)) {
                    Log::debug('Spectre: Is ready for conversion, so send to conversion.');
                    $route = route('007-convert.index');
                    Log::debug(sprintf('Spectre: Return redirect to "%s"', $route));

                    return redirect($route);
                }
                Log::debug('Spectre: Is ready for submit.');
                // otherwise go to import right away
                $route = route('008-submit.index');
                Log::debug(sprintf('Spectre: Return redirect to "%s"', $route));

                return redirect($route);

            case 'conversion':
                if (session()->has(Constants::READY_FOR_SUBMISSION) && true === session()->get(Constants::READY_FOR_SUBMISSION)) {
                    $route = route('008-submit.index');
                    Log::debug(sprintf('Return redirect to "%s"', $route));

                    return redirect($route);
                }
        }

        throw new ImporterErrorException(sprintf('redirectToCorrectSpectreStep: Cannot handle basic step "%s"', self::STEP));
    }

    private function redirectToCorrectSimpleFINStep(): RedirectResponse
    {
        Log::debug(sprintf('redirectToCorrectSimpleFINStep("%s")', self::STEP));

        switch (self::STEP) {
            default:
                throw new ImporterErrorException(sprintf('redirectToCorrectSimpleFINStep: Cannot handle SimpleFIN step "%s"', self::STEP));

            case 'authenticate':
                // simplefin does not authenticate, redirect to upload step.
                $route = route('003-upload.index');
                Log::debug(sprintf('SimpleFIN: Return redirect to "%s"', $route));

                return redirect($route);

            case 'configuration':
                Log::debug(sprintf('SimpleFIN: Not ready for configuration (STEP: "%s"), redirecting to upload.', self::STEP));

                return redirect(route('003-upload.index'));

            case 'define-roles':
                $route = route('007-convert.index');
                Log::debug(sprintf('SimpleFIN: Return redirect to "%s"', $route));

                return redirect($route);

            case 'map':
                // if conversion not complete yet, go there first
                if (!session()->has(Constants::CONVERSION_COMPLETE_INDICATOR) || true !== session()->get(Constants::CONVERSION_COMPLETE_INDICATOR)) {
                    Log::debug('SimpleFIN: Conversion not complete, redirecting to conversion.');
                    $route = route('007-convert.index');
                    Log::debug(sprintf('SimpleFIN: Return redirect to "%s"', $route));

                    return redirect($route);
                }
                Log::debug('SimpleFIN: Conversion complete, redirecting to configuration.');
                // conversion complete but no mapping data yet, redirect to configuration
                $route = route('004-configure.index');
                Log::debug(sprintf('SimpleFIN: Return redirect to "%s"', $route));

                return redirect($route);

            case 'conversion':
                // This case is reached if isReadyForSimpleFINStep() returned false for 'conversion',
                // meaning Constants::READY_FOR_CONVERSION was not true.
                // The user should be redirected to the configuration step, which is the prerequisite.
                Log::debug(sprintf('SimpleFIN: Not ready for conversion (STEP: "%s"), redirecting to configuration.', self::STEP));
                $route = route('004-configure.index');

                return redirect($route);

            case 'submit':
                $route = route('007-convert.index');
                Log::debug(sprintf('SimpleFIN: Return redirect to "%s"', $route));

                return redirect($route);
        }
    }

    /**
     * @throws ImporterErrorException
     */
    private function redirectToBasicStep(): RedirectResponse
    {
        Log::debug(sprintf('redirectToBasicStep("%s")', self::STEP));

        // @noinspection PhpSwitchStatementWitSingleBranchInspection
        switch (self::STEP) {
            default:
                throw new ImporterErrorException(sprintf('redirectToBasicStep: Cannot handle basic step "%s"', self::STEP));
        }
    }
}
