<?php
/*
 * Constants.php
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

namespace App\Services\Session;

/**
 * Class Constants
 */
class Constants
{
    // constants to remember Nordigen access token, refresh token and validity:
    public const string ASSET_ACCOUNTS                = 'assets';
    public const string CONFIGURATION                 = 'configuration';
    public const string CONFIG_COMPLETE_INDICATOR     = 'config_complete';
    public const string CONFIG_FILE_PATHS             = 'config_file_paths';

    // session value constants:
    public const string CONNECTION_SELECTED_INDICATOR = 'connection_selected_ind';
    public const string CONVERSION_COMPLETE_INDICATOR = 'conversion_complete';
    public const string CONVERSION_JOB_IDENTIFIER     = 'conversion_job_id';
    public const string FLOW_COOKIE                   = 'flow';

    // upload config values:
    public const string HAS_UPLOAD                    = 'has_uploaded_file';
    public const string IMPORT_FILE_PATHS             = 'upload_file_paths';

    // cookie name to remember the flow:
    public const string IMPORT_JOB_IDENTIFIER         = 'import_job_id';

    // stores the configuration array
    public const string LIABILITIES                   = 'liabilities';

    // if the user is done with specific steps:
    public const string MAPPING_COMPLETE_INDICATOR    = 'mapping_config_complete';
    public const string NORDIGEN_ACCESS_EXPIRY_TIME   = 'nordigen_access_expiry_time';
    public const string NORDIGEN_ACCESS_TOKEN         = 'nordigen_access_token';
    public const string NORDIGEN_REFRESH_EXPIRY_TIME  = 'nordigen_refresh_expiry_time';
    public const string NORDIGEN_REFRESH_TOKEN        = 'nordigen_refresh_token';
    public const string READY_FOR_CONVERSION          = 'ready_for_conversion';
    public const string READY_FOR_SUBMISSION          = 'ready_for_submission';
    public const string REQUISITION_PRESENT           = 'requisition_present';
    public const string REQUISITION_REFERENCE         = 'requisition_reference';

    // spectre specific steps:
    public const string ROLES_COMPLETE_INDICATOR      = 'role_config_complete';

    // nordigen specific steps
    public const string SELECTED_BANK_COUNTRY         = 'selected_bank_country';

    // nordigen specific constants
    public const string SESSION_ACCESS_TOKEN          = 'session_token';

    // constants for data conversion job:
    public const string SESSION_BASE_URL              = 'base_url';
    public const string SESSION_CLIENT_ID             = 'client_id';

    // specific variables for the ability to upload multiple (config) files at once
    public const string SESSION_NORDIGEN_ID           = 'nordigen_id';
    public const string SESSION_NORDIGEN_KEY          = 'nordigen_key';

    // other variables
    public const string SESSION_REFRESH_TOKEN         = 'refresh_token';
    public const string SESSION_SPECTRE_APP_ID        = 'spectre_app_id';

    // session variable names:
    public const string SESSION_SPECTRE_SECRET        = 'spectre_secret';
    public const string SESSION_VANITY_URL            = 'vanity_url';
    public const string SUBMISSION_COMPLETE_INDICATOR = 'submission_complete';
    public const string UPLOAD_CONFIG_FILE            = 'config_file_path';
    public const string UPLOAD_DATA_FILE              = 'data_file_path';
}
