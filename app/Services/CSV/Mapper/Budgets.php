<?php
declare(strict_types=1);
/**
 * Budgets.php
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

namespace App\Services\CSV\Mapper;

use App\Exceptions\ImportException;
use App\Support\Token;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Budget;
use GrumpyDictator\FFIIIApiSupport\Request\GetBudgetsRequest;
use Log;

/**
 * Class Budgets
 */
class Budgets implements MapperInterface
{

    /**
     * Get map of objects.
     *
     * @return array
     * @throws ImportException
     */
    public function getMap(): array
    {
        $result  = [];
        $url     = Token::getURL();
        $token   = Token::getAccessToken();
        $request = new GetBudgetsRequest($url, $token);

        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));

        try {
            $response = $request->get();
        } catch (ApiHttpException $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            throw new ImportException(sprintf('Could not download budgets: %s', $e->getMessage()));
        }


        /** @var Budget $budget */
        foreach ($response as $budget) {
            $result[$budget->id] = sprintf('%s', $budget->name);
        }
        asort($result, SORT_STRING);

        return $result;
    }
}
