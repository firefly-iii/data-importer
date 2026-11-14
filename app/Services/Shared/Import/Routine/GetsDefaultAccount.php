<?php

declare(strict_types=1);

/*
 * GetsDefaultAccount.php
 * Copyright (c) 2026 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
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

namespace App\Services\Shared\Import\Routine;

use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Authentication\SecretManager;
use App\Support\RequestCache;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountResponse;
use Illuminate\Support\Facades\Log;

trait GetsDefaultAccount
{
    protected Account $defaultAccount;

    /**
     * @throws ImporterErrorException
     */
    protected function getDefaultAccount(?int $accountId): void
    {
        $url      = SecretManager::getBaseUrl();
        $token    = SecretManager::getAccessToken();
        $cacheKey = sprintf('%s-%s-%s', $url, $accountId, 'getDefaultAccount');

        if (null !== $accountId) {
            // in cache perhaps?
            $inCache              = RequestCache::hasKeyInCache($cacheKey, $token);
            if ($inCache) {
                $this->defaultAccount = RequestCache::get($cacheKey, $token);

                return;
            }

            $accountRequest       = new GetAccountRequest($url, $token);
            $accountRequest->setVerify(config('importer.connection.verify'));
            $accountRequest->setTimeOut(config('importer.connection.timeout'));
            $accountRequest->setId($accountId);

            try {
                /** @var GetAccountResponse $result */
                $result = $accountRequest->get();
            } catch (ApiHttpException $e) {
                Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

                throw new ImporterErrorException(sprintf('The default account in your configuration file (%d) does not exist.', $accountId));
            }
            $this->defaultAccount = $result->getAccount();
            RequestCache::set($cacheKey, $token, $this->defaultAccount);
        }
    }
}
