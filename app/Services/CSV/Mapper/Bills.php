<?php
/*
 * Bills.php
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

namespace App\Services\CSV\Mapper;

use App\Exceptions\ImporterErrorException;
use App\Support\Token;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Bill;
use GrumpyDictator\FFIIIApiSupport\Request\GetBillsRequest;

/**
 * Class Bills
 */
class Bills implements MapperInterface
{
    /**
     * Get map of objects.
     *
     * @return array
     * @throws ImporterErrorException
     */
    public function getMap(): array
    {
        $result  = [];
        $url     = Token::getURL();
        $token   = Token::getAccessToken();
        $request = new GetBillsRequest($url, $token);

        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));

        try {
            $response = $request->get();
        } catch (ApiHttpException $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            throw new ImporterErrorException(sprintf('Could not download bills: %s', $e->getMessage()));
        }
        /** @var Bill $bill */
        foreach ($response as $bill) {
            $result[$bill->id] = sprintf('%s (%s)', $bill->name, $bill->repeat_freq);
        }
        asort($result, SORT_STRING);

        return $result;
    }
}
