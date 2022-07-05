<?php
/*
 * ValidatesCombinations.php
 * Copyright (c) 2022 james@firefly-iii.org
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

namespace App\Support\Http;

use App\Exceptions\ImporterErrorException;
use App\Services\Session\Constants;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Trait ValidatesCombinations
 */
trait ValidatesCombinations
{
    /**
     * @return void
     * @throws ImporterErrorException
     */
    protected function validatesCombinations(): void
    {

        try {
            $combinations = session()->get(Constants::UPLOADED_COMBINATIONS);
        } catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
            throw new ImporterErrorException($e->getMessage(), $e->getCode(), $e);
        }
        if (!is_array($combinations)) {
            throw new ImporterErrorException('Combinations must be an array.');
        }
        if (count($combinations) < 1) {
            throw new ImporterErrorException('Combinations must be more than zero.');
        }
    }

}
