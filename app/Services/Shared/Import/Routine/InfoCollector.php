<?php

/*
 * InfoCollector.php
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

declare(strict_types=1);

namespace App\Services\Shared\Import\Routine;

use App\Services\Shared\Authentication\SecretManager;
use App\Support\RequestCache;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use Illuminate\Support\Facades\Log;

/**
 * Class InfoCollector
 */
class InfoCollector
{
    /**
     * Collect various accounts from Firefly III and save the account type.
     *
     * Will be used in mapping routine.
     *
     * @throws ApiHttpException
     */
    public function collectAccountTypes(): array
    {
        app('log')->debug('Now in collectAccountTypes()');
        // get list of asset accounts:
        $url    = SecretManager::getBaseUrl();
        $token  = SecretManager::getAccessToken();
        $cacheKey = sprintf('%s-%s', $url, 'collectAccountTypes');
        $return = [];
        $count  = 0;

        $inCache = RequestCache::has($cacheKey, $token);
        if (!$inCache) {
            Log::debug('Get response fresh!');
            $request = new GetAccountsRequest($url, $token);
            $request->setType(GetAccountsRequest::ALL);
            $request->setVerify(config('importer.connection.verify'));
            $request->setTimeOut(config('importer.connection.timeout'));
            $response = $request->get();
        }
        if ($inCache) {
            Log::debug('Get response from cache!');
            return RequestCache::get($cacheKey, $token);
        }
        /** @var Account $account */
        foreach ($response as $account) {
            $return[$account->id] = $account->type;
            ++$count;
        }
        app('log')->debug(sprintf('Collected %d account(s) in collectAccountTypes()', $count));

        RequestCache::set($cacheKey, $token, $return);
        return $return;
    }
}
