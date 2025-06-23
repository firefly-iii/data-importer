<?php

/*
 * TransactionsRequest.php
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

namespace App\Services\SimpleFIN\Request;

use App\Exceptions\ImporterHttpException;
use App\Services\SimpleFIN\Response\TransactionsResponse;
use App\Services\Shared\Response\ResponseInterface as SharedResponseInterface;

/**
 * Class TransactionsRequest
 */
class TransactionsRequest extends SimpleFINRequest
{
    /**
     * @throws ImporterHttpException
     */
    public function get(): SharedResponseInterface
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));

        $response = $this->authenticatedGet('');

        return new TransactionsResponse($response);
    }
}