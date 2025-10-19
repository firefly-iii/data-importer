<?php

/*
 * ExitCode.php
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

namespace App\Enums;

enum ExitCode: int
{
    case SUCCESS                     = 0;
    case GENERAL_ERROR               = 1;
    case NO_CONNECTION               = 64;
    case INVALID_PATH                = 65;
    case NOT_ALLOWED_PATH            = 66;
    case NO_FILES_FOUND              = 67;
    case CANNOT_READ_CONFIG          = 68;
    case CANNOT_PARSE_CONFIG         = 69;
    case IMPORTABLE_FILE_NOT_FOUND   = 70;
    case CANNOT_READ_IMPORTABLE_FILE = 71;
    case TOO_MANY_ERRORS_PROCESSING  = 72;
    case NOTHING_WAS_IMPORTED        = 73;
    case AGREEMENT_EXPIRED           = 74;

}
