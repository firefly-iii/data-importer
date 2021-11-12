<?php
declare(strict_types=1);
/**
 * Categories.php
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
use GrumpyDictator\FFIIIApiSupport\Model\Category;
use GrumpyDictator\FFIIIApiSupport\Request\GetCategoriesRequest;
use Log;

/**
 * Class Categories
 */
class Categories implements MapperInterface
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
        $request = new GetCategoriesRequest($url, $token);

        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));

        try {
            $response = $request->get();
        } catch (ApiHttpException $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            throw new ImportException(sprintf('Could not download categories: %s', $e->getMessage()));
        }
        /** @var Category $category */
        foreach ($response as $category) {
            $result[$category->id] = sprintf('%s', $category->name);
        }
        asort($result, SORT_STRING);

        return $result;
    }
}
