<?php

namespace App\Api\Controllers\ImportJob;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;

/*
 * IndexController.php
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


class IndexController extends BaseController
{

    public function index(): JsonResponse
    {
        $disk  = Storage::disk('import-jobs');
        $files = [];
        foreach ($disk->allFiles() as $file) {
            if ('json' === $this->getExtension($file)) {
                $files[] = $file;
            }
        }
        return response()->json($files);
    }

    private function getExtension(string $name): string
    {
        $parts = explode('.', $name);
        if (count($parts) > 1) {
            return $parts[count($parts) - 1];
        }

        return '';
    }

}
