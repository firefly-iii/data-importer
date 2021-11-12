<?php
declare(strict_types=1);
/**
 * StartImport.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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

namespace App\Console;

use App\Exceptions\ImportException;
use App\Services\CSV\Configuration\Configuration;
use App\Services\CSV\File\FileReader;
use App\Services\Import\ImportRoutineManager;
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
        $configObject = Configuration::fromFile($configuration);
        $manager      = new ImportRoutineManager;

        try {
            $manager->setConfiguration($configObject);
        } catch (ImportException $e) {
            $this->error($e->getMessage());

            return 1;
        }
        $manager->setReader(FileReader::getReaderFromContent($csv));
        $manager->start();

        $this->messages = $manager->getAllMessages();
        $this->warnings = $manager->getAllWarnings();
        $this->errors   = $manager->getAllErrors();

        $this->listMessages('ERROR', $this->errors);
        $this->listMessages('Warning', $this->warnings);
        $this->listMessages('Message', $this->messages);

        return 0;
    }
}
