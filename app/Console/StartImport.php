<?php
/*
 * StartImport.php
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

use App\Exceptions\ImporterErrorException;
use App\Services\CSV\Configuration\Configuration;
use App\Services\CSV\File\FileReader;
use Log;

/**
 * Trait StartImport
 */
trait StartImport
{
    use ManageMessages;

    protected array $messages;
    protected array $warnings;
    protected array $errors;

    /**
     * @param string $csv
     * @param array  $configuration
     *
     * @return int
     */
    private function startImport(string $csv, array $configuration): int
    {
        $this->messages = [];
        $this->warnings = [];
        $this->errors   = [];

        Log::debug(sprintf('Now in %s', __METHOD__));
        // TODO this is where the import routine must be called. See file history.

        return 1;
    }
}
