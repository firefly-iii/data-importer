<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Shared\Configuration\Configuration;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class UpgradeImportConfigurations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'importer:upgrade-import-configurations {directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pointed to a directory, will parse and OVERWRITE all JSON files found there according to the latest JSON configuration file standards.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $directory = (string) $this->argument('directory');

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

    /**
     * @param string $directory
     */
    private function processRoot(string $directory): void
    {
        $dir   = new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);
        /**
         * @var string      $name
         * @var SplFileInfo $object
         */
        foreach ($files as $name => $object) {
            $this->processFile($name);
        }
    }

    /**
     * @param string $name
     */
    private function processFile(string $name): void
    {
        if ('json' !== $this->getExtension($name) || is_dir($name)) {
            return;
        }
        $this->line(sprintf('Now processing "%s" ...', $name));
        $content = (string) file_get_contents($name);
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

    /**
     * @param string $name
     * @return string
     */
    private function getExtension(string $name): string
    {
        $parts = explode('.', $name);

        return $parts[count($parts) - 1];
    }

    /**
     * @param string $content
     * @return bool
     */
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
}
