<?php
/*
 * ImportReportMail.php
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

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Class ImportReportMail
 */
class ImportReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $time;
    public array  $errors;
    public array  $warnings;
    public array  $messages;
    public string $url;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(array $log)
    {
        $this->time     = date('Y-m-d \@ H:i:s');
        $this->url      = config('importer.url');
        $this->errors   = $log['errors'] ?? [];
        $this->warnings = $log['warnings'] ?? [];
        $this->messages = $log['messages'] ?? [];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $address = (string) config('mail.from.address');
        $name    = (string) config('mail.from.name');

        return $this->from($address, $name)->markdown('emails.import.report');
    }
}
