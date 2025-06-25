<?php

/*
 * NavController.php
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

namespace App\Http\Controllers;

use App\Services\Session\Constants;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * Class NavController
 */
class NavController extends Controller
{
    /**
     * Return back to config
     */
    public function toConfig(): RedirectResponse
    {
        Log::debug(__METHOD__);

        // For SimpleFIN flow, don't forget CONFIG_COMPLETE_INDICATOR to preserve form state
        $sessionConfig = session()->get(Constants::CONFIGURATION);
        $flow          = null;
        if (is_array($sessionConfig) && array_key_exists('flow', $sessionConfig) && null !== $sessionConfig['flow']) {
            $flow = $sessionConfig['flow'];
        }

        if ('simplefin' !== $flow) {
            session()->forget(Constants::CONFIG_COMPLETE_INDICATOR);
        }

        return redirect(route('004-configure.index').'?overruleskip=true');
    }

    public function toConversion(): RedirectResponse
    {
        Log::debug(__METHOD__);
        session()->forget(Constants::CONVERSION_COMPLETE_INDICATOR);

        return redirect(route('005-roles.index'));
    }

    public function toRoles(): RedirectResponse
    {
        Log::debug(__METHOD__);
        session()->forget(Constants::ROLES_COMPLETE_INDICATOR);

        return redirect(route('005-roles.index'));
    }

    /**
     * Return back to index. Needs no session updates.
     */
    public function toStart(): RedirectResponse
    {
        Log::debug(__METHOD__);

        return redirect(route('index'));
    }

    /**
     * Return back to upload.
     */
    public function toUpload(): RedirectResponse
    {
        Log::debug(__METHOD__);
        session()->forget(Constants::HAS_UPLOAD);

        return redirect(route('003-upload.index'));
    }
}
