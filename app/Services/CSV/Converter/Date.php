<?php

/*
 * Date.php
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

namespace App\Services\CSV\Converter;

use Carbon\Carbon;
use Carbon\Language;
use Illuminate\Support\Facades\Log;

/**
 * Class Date
 */
class Date implements ConverterInterface
{
    private string $dateFormat;
    private string $dateFormatPattern;
    private string $dateLocale;

    /**
     * Date constructor.
     */
    public function __construct()
    {
        $this->dateFormat        = 'Y-m-d';
        $this->dateLocale        = 'en';
        $this->dateFormatPattern = '/(?:('.implode('|', array_keys(Language::all())).')\:)?(.+)/';
    }

    /**
     * Convert a value.
     *
     * @param mixed $value
     *
     * @return string
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
            } catch (\Exception|\InvalidArgumentException $e) {
                Log::error(sprintf('%s converting the date: %s', get_class($e), $e->getMessage()));
                Log::debug('Date parsing error, will return today instead.');

                return Carbon::today()->startOfDay()->format('Y-m-d H:i:s');
            }
        }
        if ($carbon->year < 1984) {
            Log::warning(sprintf('Date year is before than 1984 ("%s"), so year is set to today.', $carbon->format('Y-m-d H:i:s')));
            $carbon->year = now()->year;
            $carbon->startOfDay();
        }

        return $carbon->format('Y-m-d H:i:s');
    }

    /**
     * Add extra configuration parameters.
     */
    public function setConfiguration(string $configuration): void
    {
        [$this->dateLocale, $this->dateFormat] = $this->splitLocaleFormat($configuration);
    }

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
