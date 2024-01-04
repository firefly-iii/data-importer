<?php

/*
 * UpgradeImportConfigurations.php
 * Copyright (c) 2022 james@firefly-iii.org
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

namespace App\Console\Commands;

use App\Services\Shared\Configuration\Configuration;
use Illuminate\Console\Command;

final class UpgradeImportConfigurations extends Command
{
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pointed to a directory, will parse and OVERWRITE all JSON files found there according to the latest JSON configuration file standards.';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature   = 'importer:upgrade-import-configurations {directory}';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $directory = (string)$this->argument('directory'); // @phpstan-ignore-line

        if (!file_exists($directory)) {
            $this->error(sprintf('"%s" does not exist.', $directory));

            return 1;
        }
        if (!is_dir($directory)) {
            $this->error(sprintf('"%s" is not a directory.', $directory));

            return 1;
        }

        $this->processRoot($directory);

        return 0;
    }

    private function getExtension(string $name): string
    {
        $parts = explode('.', $name);

        return $parts[count($parts) - 1];
    }

    private function isValidJson(string $content): bool
    {
        if ('' === $content) {
            return false;
        }
        $json = json_decode($content, true);
        if (false === $json) {
            return false;
        }

        return true;
    }

    private function processFile(string $name): void
    {
        if ('json' !== $this->getExtension($name) || is_dir($name)) {
            return;
        }
        $this->line(sprintf('Now processing "%s" ...', $name));
        $content                    = (string)file_get_contents($name);
        if (!$this->isValidJson($content)) {
            $this->error('File does not contain valid JSON. Skipped.');

            return;
        }
        $configuration              = Configuration::fromFile(json_decode($content, true));
        $newJson                    = $configuration->toArray();
        $newJson['mapping']         = [];
        $newJson['default_account'] = 0;
        file_put_contents($name, json_encode($newJson, JSON_PRETTY_PRINT));
    }

    private function processRoot(string $directory): void
    {
        $dir   = new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($dir, \RecursiveIteratorIterator::SELF_FIRST);

        /**
         * @var string       $name
         * @var \SplFileInfo $object
         */
        foreach ($files as $name => $object) {
            $this->processFile($name);
        }
    }
}
