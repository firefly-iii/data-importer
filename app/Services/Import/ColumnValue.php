<?php
/**
 * ColumnValue.php
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

namespace App\Services\Import;

use App\Services\CSV\Converter\ConverterService;
use Log;

/**
 * Class ColumnValue
 */
class ColumnValue
{
    /** @var int */
    private $mappedValue;
    /** @var string */
    private $originalRole;
    /** @var string */
    private $role;
    /** @var string */
    private $value;
    /** @var string */
    private $configuration;
    /** @var bool */
    private $appendValue;

    /**
     * ColumnValue constructor.
     */
    public function __construct()
    {
        $this->mappedValue = 0;
    }

    /**
     * @return bool
     */
    public function isAppendValue(): bool
    {
        return $this->appendValue;
    }

    /**
     * @param bool $appendValue
     */
    public function setAppendValue(bool $appendValue): void
    {
        $this->appendValue = $appendValue;
    }

    /**
     * @return int
     */
    public function getMappedValue(): int
    {
        return $this->mappedValue;
    }

    /**
     * @param int $mappedValue
     */
    public function setMappedValue(int $mappedValue): void
    {
        $this->mappedValue = $mappedValue;
    }

    /**
     * @param string $configuration
     */
    public function setConfiguration(string $configuration): void
    {
        $this->configuration = $configuration;
    }

    /**
     * @return string
     */
    public function getOriginalRole(): string
    {
        return $this->originalRole;
    }

    /**
     * @param string $originalRole
     */
    public function setOriginalRole(string $originalRole): void
    {
        $this->originalRole = $originalRole;
    }

    /**
     * @return mixed
     */
    public function getParsedValue()
    {
        if (0 !== $this->mappedValue) {
            /** @noinspection UnnecessaryCastingInspection */
            return (int) $this->mappedValue;
        }

        // run converter on data:
        $converterClass = (string) config(sprintf('csv_importer.import_roles.%s.converter', $this->role));
        Log::debug(sprintf('getParsedValue will run %s', $converterClass));
        return ConverterService::convert($converterClass, $this->value, $this->configuration);
    }

    /**
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @param string $role
     */
    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }


}
