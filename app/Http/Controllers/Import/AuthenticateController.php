<?php
/*
 * AuthenticateController.php
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

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Services\Session\Constants;
use Illuminate\Http\Request;

/**
 * Class AuthenticateController
 */
class AuthenticateController extends Controller
{
    /**
     * @param Request $request
     */
    public function index(Request $request)
    {
        $flow = $request->cookie(Constants::FLOW_COOKIE);
        if ('csv' === $flow) {
            // redirect straight to upload
            return redirect(route('003-upload.index'));
        }

        exit; // here we are deze functies moeten iets anders teruggeven.
        // dus niet true/false of null/string maar evt een error ofzo?
        // plus wat als de values leeg zijn geeft-ie dan false terug of wat anders?
        $verifySpectre = $this->verifySpectre();
        if ('spectre' === $flow && null === $verifySpectre) {
            return redirect(route('003-upload.index'));
        }

        $verifyNordigen = $this->verifyNordigen();
        if ('spectre' === $flow && null === $verifySpectre) {
            return redirect(route('003-upload.index'));
        }
    }

}
