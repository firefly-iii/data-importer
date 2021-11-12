<?php
declare(strict_types=1);
/**
 * SpecificService.php
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

namespace App\Services\CSV\Specifics;

use Log;

/**
 * Class SpecificService
 */
class SpecificService
{
    /**
     * @return array
     */
    public static function getSpecifics(): array
    {
        $specifics = [];
        foreach (config('csv_importer.specifics') as $class) {
            if (class_exists($class)) {
                $parts           = explode('\\', $class);
                $key             = $parts[count($parts) - 1];
                $specifics[$key] = [
                    'name'        => trans($class::getName()),
                    'description' => trans($class::getDescription()),
                ];
            }
        }

        return $specifics;
    }

    /**
     * @param array $row
     * @param array $specifics
     *
     * @return array
     */
    public static function runSpecifics(array $row, array $specifics): array
    {
        // if the array keys are strings, pluck those.
        if (empty($specifics)) {
            Log::debug('Not configured to run any specifics on this row.');
            return $row;
        }
        Log::debug(sprintf('Going to run %d specifics on this row.', count($specifics)), $specifics);

        // little hack.
        $newSpecifics = $specifics;
        $keys         = array_keys($specifics);
        if (is_string($keys[0]) || !is_numeric($keys[0])) {
            $newSpecifics = [];
            foreach ($specifics as $name => $enabled) {
                if ($enabled) {
                    $newSpecifics[] = $name;
                }
            }
        }

        // Fix as suggested by @FelikZ in https://github.com/firefly-iii/csv-importer/pull/4
        /** @var string $name */
        foreach ($newSpecifics as $name) {
            if (self::exists($name)) {
                /** @var SpecificInterface $object */
                $object = app(self::fullClass($name));
                $row    = $object->run($row);
            }
        }

        return $row;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public static function exists(string $name): bool
    {
        return class_exists(self::fullClass($name));
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public static function fullClass(string $name): string
    {
        return sprintf('App\\Services\\CSV\\Specifics\\%s', $name);
    }

}
