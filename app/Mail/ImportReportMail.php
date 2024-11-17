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
    use Queueable;
    use SerializesModels;

    public array  $errors;
    public array  $messages;
    public string $time;
    public string $url;
    public string $version;
    public array  $warnings;
    public array  $rateLimits;

    /**
     * Create a new message instance.
     */
    public function __construct(array $log)
    {
        $this->time       = date('Y-m-d \@ H:i:s');
        $this->url        = (string) config('importer.url');
        $this->version    = config('importer.version');
        if ('' !== (string) config('importer.vanity_url')) {
            $this->url = (string) config('importer.vanity_url');
        }
        $this->errors     = $log['errors'] ?? [];
        $this->warnings   = $log['warnings'] ?? [];
        $this->messages   = $log['messages'] ?? [];
        $this->rateLimits = $log['rate_limits'] ?? [];
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
