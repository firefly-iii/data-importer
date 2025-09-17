<?php

namespace App\Console\Commands;

use App\Console\VerifyJSON;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ValidateJsonFiles extends Command
{
    use VerifyJSON;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:validate-json-directory {directory : The directory with JSON files to validate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recursively validate all JSON files in a directory. Stops after 100 files.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $directory = (string)$this->argument('directory');
        if (!is_dir($directory) || !is_readable($directory)) {
            $this->error(sprintf('Cannot read directory %s.', $directory));
            return CommandAlias::FAILURE;
        }

        // check each file in the directory and see if it needs action.
        // collect recursively:
        $it        = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS));
        $Regex     = new \RegexIterator($it, '/^.+\.json$/i', \RecursiveRegexIterator::GET_MATCH);
        $fullPaths = [];
        foreach ($Regex as $item) {
            $path        = $item[0];
            $fullPaths[] = $path;
        }
        foreach ($fullPaths as $file) {
            $result = $this->verifyJSON($file);
            if (false === $result) {
                $this->error(sprintf('File "%s" is not valid JSON.', $file));
                return CommandAlias::FAILURE;
            }
            $this->info(sprintf('File "%s" is valid JSON.', $file));
        }
        return CommandAlias::SUCCESS;
    }
}
