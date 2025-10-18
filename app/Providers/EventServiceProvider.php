<?php

/*
 * EventServiceProvider.php
 * Copyright (c) 2025 james@firefly-iii.org
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

namespace App\Providers;

use App\Events\CompletedConfiguration;
use App\Events\ImportedTransactions;
use App\Events\ProvidedConfigUpload;
use App\Events\ProvidedDataUpload;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Class EventServiceProvider
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     */
    protected $listen
        = [
            Registered::class           => [
                SendEmailVerificationNotification::class,
            ],
            ImportedTransactions::class => [
                'App\Handlers\Events\ImportedTransactionsEventHandler@sendReportOverMail',
            ],
            CompletedConfiguration::class => [
                'App\Handlers\Events\ImportFlowHandler@handleCompletedConfiguration',
            ],
            ProvidedDataUpload::class => [
                'App\Handlers\Events\ImportFlowHandler@handleProvidedDataUpload',
            ],
            ProvidedConfigUpload::class => [
                'App\Handlers\Events\ImportFlowHandler@handleProvidedConfigUpload',
            ]
        ];
}
