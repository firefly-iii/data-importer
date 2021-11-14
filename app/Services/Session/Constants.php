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
    public const NORDIGEN_ACCESS_TOKEN        = 'nordigen_access_token';
    public const NORDIGEN_REFRESH_TOKEN       = 'nordigen_refresh_token';
    public const NORDIGEN_ACCESS_EXPIRY_TIME  = 'nordigen_access_expiry_time';
    public const NORDIGEN_REFRESH_EXPIRY_TIME = 'nordigen_refresh_expiry_time';

    // session value constants:
    public const SESSION_SPECTRE_APP_ID = 'spectre_app_id';
    public const SESSION_SPECTRE_SECRET = 'spectre_secret';
    public const SESSION_NORDIGEN_ID    = 'nordigen_id';
    public const SESSION_NORDIGEN_KEY   = 'nordigen_key';

    // upload config values:
    public const UPLOAD_CSV_FILE    = 'csv_file_path';
    public const UPLOAD_CONFIG_FILE = 'config_file_path';

    // cookie name to remember the flow:
    public const FLOW_COOKIE = 'flow';

    //     stores the configuration array
    public const CONFIGURATION = 'configuration';

    // if the user is done with specific steps:
    public const HAS_UPLOAD                    = 'has_uploaded_file';
    public const CONFIG_COMPLETE_INDICATOR     = 'config_complete';
    public const ROLES_COMPLETE_INDICATOR      = 'role_config_complete';
    public const MAPPING_COMPLETE_INDICATOR    = 'mapping_config_complete';
    public const CONVERSION_COMPLETE_INDICATOR = 'conversion_complete';

    // constants for data conversion job:
    public const CONVERSION_JOB_IDENTIFIER = 'conversion_job_id';
    public const IMPORT_JOB_IDENTIFIER     = 'import_job_id';


    //    /** @var string */
//    /** @var string */
//    public const JOB_STATUS = 'import_job_status';
//    /** @var string */
//    /** @var string string */
//    /** @var string */

//    /** @var string */

}
