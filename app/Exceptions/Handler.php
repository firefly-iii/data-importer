<?php

/*
 * Handler.php
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

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Override;
use Throwable;

/**
 * Class Handler
 */
class Handler extends ExceptionHandler
{
    protected $dontFlash
        = [
            'password',
            'password_confirmation',
        ];

    protected $dontReport
        = [
        ];

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|JsonResponse|Response
     *
     * @throws Throwable
     */
    #[Override]
    public function render($request, Throwable $e)
    {
        if ($e instanceof ImporterErrorException || $e instanceof ImporterHttpException) {
            $isDebug = config('app.debug');

            return response()->view('errors.exception', ['exception' => $e, 'debug' => $isDebug], 500);
        }

        return parent::render($request, $e);
    }
}
