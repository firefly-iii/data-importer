<?php
/*
 * ValidateJsonFile.php
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

namespace App\Console\Commands;

use App\Console\VerifyJSON;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ValidateJsonFile extends Command
{
    use VerifyJSON;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature   = 'import:validate-json {file : The JSON file to validate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks if a JSON file is valid according to the v3 import configuration file standard.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $file   = (string)$this->argument('file');
        if (!is_file($file) || !is_readable($file)) {
            $this->error(sprintf('File %s does not exist or is not readable.', $file));

            return CommandAlias::FAILURE;
        }
        $result = $this->verifyJSON($file);
        if (false === $result) {
            $this->error('File is not valid JSON.');

            return CommandAlias::FAILURE;
        }

        $this->info('File is valid JSON.');

        return CommandAlias::SUCCESS;
    }
}
