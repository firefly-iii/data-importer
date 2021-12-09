<?php
/*
 * ImportedTransactionsEventHandler.php
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

namespace App\Handlers\Events;

use App\Events\ImportedTransactions;
use App\Mail\ImportReportMail;
use Illuminate\Support\Facades\Mail;

class ImportedTransactionsEventHandler
{
    /**
     * @param ImportedTransactions $event
     */
    public function sendReportOverMail(ImportedTransactions $event): void
    {
        app('log')->debug('Now in sendReportOverMail');

        $mailer = config('mail.default');
        $receiver = config('mail.destination');
        if('' === $mailer) {
            app('log')->info('No mailer configured, will not mail.');
            return;
        }
        if('' === $receiver) {
            app('log')->info('No mail receiver configured, will not mail.');
            return;
        }

        $log  =[
            'messages' => $event->messages,
            'warnings' => $event->warnings,
            'errors' => $event->errors,
        ];
        app('log')->info('Will send report message.');
        app('log')->debug('If no error below this line, mail was sent!');
        Mail::to(config('mail.destination'))->send(new ImportReportMail($log));
        app('log')->debug('If no error above this line, mail was sent!');
    }

}
