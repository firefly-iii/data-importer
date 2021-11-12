<?php
declare(strict_types=1);
/**
 * ConverterService.php
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

namespace App\Services\CSV\Converter;

use Log;
use UnexpectedValueException;

/**
 * Class ConverterService
 */
class ConverterService
{
    /**
     * @param string      $class
     * @param mixed       $value
     * @param string|null $configuration
     *
     * @return mixed
     */
    public static function convert(string $class, $value, ?string $configuration)
    {
        if ('' === $class) {
            return $value;
        }
        if (self::exists($class)) {
            /** @var ConverterInterface $object */
            $object = app(self::fullName($class));
            Log::debug(sprintf('Created converter class %s', $class));
            if (null !== $configuration) {
                $object->setConfiguration($configuration);
            }

            return $object->convert($value);
        }
        throw new UnexpectedValueException(sprintf('No such converter: "%s"', $class));
    }

    /**
     * @param string $class
     *
     * @return bool
     */
    public static function exists(string $class): bool
    {
        $name = self::fullName($class);

        return class_exists($name);
    }

    /**
     * @param string $class
     *
     * @return string
     */
    public static function fullName(string $class): string
    {
        return sprintf('App\\Services\\CSV\\Converter\\%s', $class);
    }

}
