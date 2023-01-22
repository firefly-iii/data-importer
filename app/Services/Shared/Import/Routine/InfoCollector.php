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
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;

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
     * @return array
     */
    public function collectAccountTypes(): array
    {
        app('log')->debug('Now in collectAccountTypes()');
        // get list of asset accounts:
        $url    = SecretManager::getBaseUrl();
        $token  = SecretManager::getAccessToken();
        $return = [];
        $count  = 0;

        $request = new GetAccountsRequest($url, $token);
        $request->setType(GetAccountsRequest::ALL);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $response = $request->get();

        /** @var Account $account */
        foreach ($response as $account) {
            $return[$account->id] = $account->type;
            $count++;
        }
        app('log')->debug(sprintf('Collected %d account(s) in collectAccountTypes()', $count));

        return $return;
    }
}
