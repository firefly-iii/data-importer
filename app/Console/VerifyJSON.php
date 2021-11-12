<?php
declare(strict_types=1);
/**
 * VerifyJSON.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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

namespace App\Console;

use Exception;
use JsonException;
use Log;

/**
 * Trait VerifyJSON
 */
trait VerifyJSON
{
    /**
     * @param string $file
     *
     * @return bool
     */
    private function verifyJSON(string $file): bool
    {
        // basic check on the JSON.
        $json = file_get_contents($file);
        try {
            json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception | JsonException $e) {
            $message = sprintf('The importer can\'t import: could not decode the JSON in the config file: %s', $e->getMessage());
            Log::error($message);

            return false;

        }

        return true;
    }

}
