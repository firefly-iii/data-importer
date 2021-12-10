<?php
declare(strict_types=1);
/*
 * ImportedTransactions.php
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

namespace App\Events;

use Illuminate\Queue\SerializesModels;


/**
 * Class ImportedTransactions
 */
class ImportedTransactions
{
    use SerializesModels;

    public array $messages;
    public array $warnings;
    public array $errors;

    /**
     * @param array $messages
     * @param array $warnings
     * @param array $errors
     */
    public function __construct(array $messages, array $warnings, array $errors)
    {
        app('log')->debug('Created event ImportedTransactions');
        $this->messages = $messages;
        $this->warnings = $warnings;
        $this->errors   = $errors;

    }
}
