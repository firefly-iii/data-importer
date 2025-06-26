<?php

/*
 * Kernel.php
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

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Override;

/**
 * Class Kernel
 */
class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands
        = [
        ];

    /**
     * Register the commands for the application.
     */
    #[Override]
    protected function commands(): void
    {
        $accessToken = (string) env('FIREFLY_III_ACCESS_TOKEN', '');
        $clientId    = (string) env('FIREFLY_III_CLIENT_ID', '');
        $baseUrl     = (string) env('FIREFLY_III_URL', '');
        $vanityUrl   = (string) env('VANITY_URL', '');
        // access token AND client ID cannot be set together
        if ('' !== $accessToken && '' !== $clientId) {
            echo PHP_EOL;
            echo 'You can\'t set FIREFLY_III_ACCESS_TOKEN together with FIREFLY_III_CLIENT_ID. One must remain empty.';
            echo PHP_EOL;

            exit;
        }
        // if vanity URL is not empty, Firefly III url must also be set.
        if ('' !== $vanityUrl && '' === $baseUrl) {
            echo PHP_EOL;
            echo 'If you set VANITY_URL you must also set FIREFLY_III_URL';
            echo PHP_EOL;

            exit;
        }

        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Define the application's command schedule.
     */
    #[Override]
    protected function schedule(Schedule $schedule): void {}
}
