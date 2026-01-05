<?php

/*
 * FileReader.php
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

namespace App\Services\CSV\File;

use Illuminate\Support\Facades\Log;
use League\Csv\Reader;

/**
 * Class FileReader
 */
class FileReader
{
    public static function getReaderFromContent(string $content, bool $convert = false): Reader
    {
        if (true === $convert) {
            $encoding = mb_detect_encoding($content, config('importer.encoding'), true);
            if (false !== $encoding && 'ASCII' !== $encoding && 'UTF-8' !== $encoding) {
                Log::warning(sprintf('Content is detected as "%s" and will be converted to UTF-8. Your milage may vary.', $encoding));
                $content = (string) mb_convert_encoding($content, 'UTF-8', $encoding);
            }
        }

        return Reader::fromString($content);
    }
}
