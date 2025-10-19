<?php

/*
 * ImportedTransactionsEventHandler.php
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

namespace App\Handlers\Events;

use App\Events\ImportedTransactions;
use App\Mail\ImportReportMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

class ImportedTransactionsEventHandler
{
    public function sendReportOverMail(ImportedTransactions $event): void
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        $mailer   = config('mail.default');
        $receiver = config('mail.destination');
        if ('' === $mailer) {
            Log::info(sprintf('[%s] No mailer configured, will not mail.', config('importer.version')));

            return;
        }
        if ('' === $receiver) {
            Log::info(sprintf('[%s] No mail receiver configured, will not mail.', config('importer.version')));

            return;
        }
        if (false === config('mail.enable_mail_report')) {
            Log::info(sprintf('[%s] Configuration does not allow mail, will not mail.', config('importer.version')));

            return;
        }

        $log      = [
            'messages'    => $event->messages,
            'warnings'    => $event->warnings,
            'errors'      => $event->errors,
            'rate_limits' => $event->rateLimits,
            'config_file' => $event->configurationFile,
        ];
        if (count($event->messages) > 0 || count($event->warnings) > 0 || count($event->errors) > 0) {
            Log::info(sprintf('[%s] Will send report message.', config('importer.version')));
            Log::debug(sprintf('Messages count: %s', count($event->messages)));
            Log::debug(sprintf('Warnings count: %s', count($event->warnings)));
            Log::debug(sprintf('Errors count  : %s', count($event->errors)));
            Log::debug(sprintf('Rate limit message count  : %s', count($event->rateLimits)));
            Log::debug('If no error below this line, mail was sent!');

            try {
                Mail::to(config('mail.destination'))->send(new ImportReportMail($log));
            } catch (TransportException $e) {
                Log::error('Could not send mail. See error below');
                Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
            }
            Log::debug('If no error above this line, mail was sent!');
        }
        if (0 === count($event->messages) && 0 === count($event->warnings) && 0 === count($event->errors)) {
            Log::info('There is nothing to report, will not send a message.');
        }
    }
}
