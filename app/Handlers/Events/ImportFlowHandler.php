<?php

declare(strict_types=1);
/*
 * ImportFlowHandler.php
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

namespace App\Handlers\Events;

use App\Events\CompletedConfiguration;
use App\Events\CompletedConversion;
use App\Events\CompletedMapping;
use App\Events\DownloadedSimpleFINAccounts;
use App\Events\ProvidedConfigUpload;
use App\Events\ProvidedDataUpload;
use App\Services\Session\Constants;
use Illuminate\Support\Facades\Log;

class ImportFlowHandler
{
    public function handleDownloadedSimpleFINAccounts(DownloadedSimpleFINAccounts $event): void {}


}
