<?php
/*
 * GetsPrimaryCurrency.php
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
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\TransactionCurrency;
use GrumpyDictator\FFIIIApiSupport\Request\GetCurrencyRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetCurrencyResponse;
use Illuminate\Support\Facades\Log;

trait GetsPrimaryCurrency
{
    protected TransactionCurrency $primaryCurrency;

    /**
     * @throws ImporterErrorException
     */
    protected function getPrimaryCurrency(): void
    {
        $url             = SecretManager::getBaseUrl();
        $token           = SecretManager::getAccessToken();

        $currencyRequest = new GetCurrencyRequest($url, $token);
        $currencyRequest->setVerify(config('importer.connection.verify'));
        $currencyRequest->setTimeOut(config('importer.connection.timeout'));
        $currencyRequest->setCode('primary');

        try {
            /** @var GetCurrencyResponse $result */
            $result                = $currencyRequest->get();
            $this->primaryCurrency = $result->getCurrency();
        } catch (ApiHttpException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

            throw new ImporterErrorException('The primary currency could not be loaded.');
        }
        Log::debug(sprintf('Currency found, default currency is assumed to be "%s" (#%d)', $this->primaryCurrency->code, $this->primaryCurrency->id));
    }
}
