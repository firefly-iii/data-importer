<?php

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
    protected $signature = 'import:validate-json {file : The JSON file to validate}';

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
        $file = (string)$this->argument('file');
        if (!is_file($file) || !is_readable($file)) {
            $this->error(sprintf('File %s does not exist or is not readable.', $file));
            return CommandAlias::FAILURE;
        }
        $result = $this->verifyJSON($file);
        if(false === $result) {
            $this->error('File is not valid JSON.');
            return CommandAlias::FAILURE;
        }

        $this->info('File is valid JSON.');
        return CommandAlias::SUCCESS;
    }
}
