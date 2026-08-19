<?php


/*
 * NewJobDataCollector.php
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

declare(strict_types=1);

namespace App\Services\Akahu\Validation;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Models\ImportJob;
use App\Services\Akahu\Request\GetAccountsRequest;
use App\Services\Shared\Validation\NewJobDataCollectorInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class NewJobDataCollector implements NewJobDataCollectorInterface
{
    private ImportJob $importJob;

    public function collectAccounts(): MessageBag
    {
        $messages = new MessageBag();
        $request  = new GetAccountsRequest();

        try {
            $response = $request->get();
        } catch (ImporterHttpException $e) {
            if (SymfonyResponse::HTTP_UNAUTHORIZED === $e->getCode()) {
                // Start a reauthentication flow
                session()->put('akahu_reauthenticate', 'true');

                $msg = 'Akahu credentials could not be authenticated, either the access tokens provided are invalid or have been revoked. You can try authenticating again or see the logs for more infomation.';

                $messages->add('no_accounts', $msg);
                Log::error($msg.' | '.$e->getMessage());

                return $messages;
            }

            if (SymfonyResponse::HTTP_FORBIDDEN === $e->getCode()) {
                // Start a reauthentication flow
                session()->put('akahu_reauthenticate', 'true');

                $msg = 'Akahu returned Forbidden when using the provided credentials, make sure all necessary permissions are granted in the Akahu website. You can try authenticating again or see the logs for more infomation.';

                $messages->add('no_accounts', $msg);
                Log::error($msg.' | '.$e->getMessage());

                return $messages;
            }

            throw new ImporterErrorException(sprintf('Failed get account details from the Akahu api: %s', $e->getMessage()));
        }

        $this->importJob->setServiceAccounts($response->getAccounts());

        return $messages;
    }

    public function validate(): MessageBag
    {
        return new MessageBag();
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function setImportJob(ImportJob $importJob): void
    {
        $this->importJob = $importJob;
    }

    public function getFlowName(): string
    {
        return 'akahu';
    }
}
