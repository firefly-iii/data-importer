<?php

/*
 * CallbackController.php
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


namespace App\Http\Controllers\Import\Spectre;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Class CallbackController
 */
class CallbackController extends Controller
{
    /**
     *
     */
    public function index(): RedirectResponse
    {
        // get all the connections the user has.
        // since this is a callback, we expect there just to be one.
        // could still be multiple. Offer choice again?
        return redirect(route('011-connections.index'));
    }
}
