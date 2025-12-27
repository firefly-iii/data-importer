<?php

/*
 * Constants.php
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

namespace App\Services\Session;

/**
 * Class Constants
 */
class Constants
{
    // constants to remember Nordigen access token, refresh token and validity:
    public const string ASSET_ACCOUNTS               = 'assets';
    public const string CONFIGURATION                = 'configuration';




    // stores the configuration array
    public const string LIABILITIES                  = 'liabilities';

    // if the user is done with specific steps:
    public const string NORDIGEN_ACCESS_EXPIRY_TIME  = 'nordigen_access_expiry_time';
    public const string NORDIGEN_ACCESS_TOKEN        = 'nordigen_access_token';
    public const string NORDIGEN_REFRESH_EXPIRY_TIME = 'nordigen_refresh_expiry_time';
    public const string NORDIGEN_REFRESH_TOKEN       = 'nordigen_refresh_token';
    public const string REQUISITION_REFERENCE        = 'requisition_reference';

    // spectre specific steps:

    // nordigen specific steps
    public const string SELECTED_BANK_COUNTRY        = 'selected_bank_country';

    // nordigen specific constants
    public const string SESSION_ACCESS_TOKEN         = 'session_token';

    // constants for data conversion job:
    public const string SESSION_BASE_URL             = 'base_url';
    public const string SESSION_CLIENT_ID            = 'client_id';

    // specific variables for the ability to upload multiple (config) files at once

    // other variables
    public const string SESSION_REFRESH_TOKEN        = 'refresh_token';

    // session variable names:
    public const string SESSION_VANITY_URL           = 'vanity_url';

}
