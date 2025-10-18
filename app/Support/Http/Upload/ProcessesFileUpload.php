<?php
/*
 * ProcessesFileUpload.php
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

namespace App\Support\Http\Upload;

use App\Events\ProvidedConfigUpload;
use App\Services\Shared\Configuration\Configuration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait ProcessesFileUpload
{

    protected function processFileUpload(Request $request, Configuration $configuration): RedirectResponse {

        event(new ProvidedConfigUpload('', $configuration));
        return redirect(route('004-configure.index'));
    }

}
