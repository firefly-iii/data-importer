<?php
declare(strict_types=1);
/**
 * Date.php
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

use Carbon\Carbon;
use Carbon\Language;
use Exception;
use InvalidArgumentException;
use Log;

/**
 * Class Date
 */
class Date implements ConverterInterface
{
    private string $dateFormat;
    private string $dateLocale;
    private string $dateFormatPattern;

    /**
     * Date constructor.
     */
    public function __construct()
    {
        $this->dateFormat        = 'Y-m-d';
        $this->dateLocale        = 'en';
        $this->dateFormatPattern = '/(?:(' . join("|", array_keys(Language::all())) . ')\:)?(.+)/';
    }

    /**
     * Convert a value.
     *
     * @param $value
     *
     * @return mixed
     *
     */
    public function convert($value)
    {
        $string = app('steam')->cleanStringAndNewlines($value);
        $carbon = null;

        if ('!' !== $this->dateFormat[0]) {
            $this->dateFormat = sprintf('!%s', $this->dateFormat);
        }

        if ('' === $string) {
            Log::warning('Empty date string, so date is set to today.');
            $carbon = today();
            $carbon->startOfDay();
        }
        if ('' !== $string) {
            Log::debug(sprintf('Date converter is going to work on "%s" using format "%s"', $string, $this->dateFormat));
            try {
                $carbon = Carbon::createFromLocaleFormat($this->dateFormat, $this->dateLocale, $string);
            } catch (InvalidArgumentException | Exception $e) {
                Log::error(sprintf('%s converting the date: %s', get_class($e), $e->getMessage()));

                return Carbon::today()->startOfDay()->format('Y-m-d H:i:s');
            }
        }

        return $carbon->format('Y-m-d H:i:s');
    }

    /**
     * Add extra configuration parameters.
     *
     * @param string $configuration
     */
    public function setConfiguration(string $configuration): void
    {
        [$this->dateLocale, $this->dateFormat] = $this->splitLocaleFormat($configuration);
    }

    /**
     * @param string $format
     * @return array|string[]
     */
    public function splitLocaleFormat(string $format): array
    {
        $currentDateLocale       = 'en';
        $currentDateFormat       = 'Y-m-d';
        $dateFormatConfiguration = [];
        preg_match($this->dateFormatPattern, $format, $dateFormatConfiguration);
        if (3 === count($dateFormatConfiguration)) {
            $currentDateLocale = $dateFormatConfiguration[1] ?: $currentDateLocale;
            $currentDateFormat = $dateFormatConfiguration[2];
        }
        return [$currentDateLocale, $currentDateFormat];
    }
}
