<?php
/**
 * AmountDebit.php
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
declare(strict_types=1);

namespace App\Services\CSV\Converter;

/**
 * Class AmountDebit
 */
class AmountDebit implements ConverterInterface
{
    /**
     * Convert amount, always return positive.
     *
     * @param $value
     *
     * @return string
     */
    public function convert($value): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        /** @var ConverterInterface $converter */
        $converter = app(Amount::class);
        $result    = $converter->convert($value);
        $result    = Amount::positive($result);
        return bcmul($result, '-1');
    }

    /**
     * Add extra configuration parameters.
     *
     * @param string $configuration
     */
    public function setConfiguration(string $configuration): void
    {

    }
}
