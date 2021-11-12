<?php


namespace App\Http\Controllers;


use App\Console\AutoImports;
use App\Console\HaveAccess;
use App\Console\StartImport;
use App\Console\VerifyJSON;
use App\Exceptions\ImportException;
use Illuminate\Http\Request;
use Log;

/**
 *
 */
class AutoImportController extends Controller
{
    use HaveAccess, AutoImports, VerifyJSON, StartImport;

    private string $directory;

    /**
     *
     */
    public function index(Request $request)
    {
        $access = $this->haveAccess();
        if (false === $access) {
            throw new ImportException('Could not connect to your local Firefly III instance.');
        }

        $argument        = (string) ($request->get('directory') ?? './');
        $this->directory = realpath($argument);
        $this->line(sprintf('Going to automatically import everything found in %s (%s)', $this->directory, $argument));

        $files = $this->getFiles();
        if (0 === count($files)) {
            $this->line(sprintf('There are no files in directory %s', $this->directory));
            $this->line('To learn more about this process, read the docs:');
            $this->line('https://docs.firefly-iii.org/csv/install/docker/');

            return ' ';
        }
        $this->line(sprintf('Found %d CSV + JSON file sets in %s', count($files), $this->directory));
        try {
            $this->importFiles($files);
        } catch (ImportException $e) {
            Log::error($e->getMessage());
            $this->line(sprintf('Import exception (see the logs): %s', $e->getMessage()));
        }

        return ' ';
    }

    /**
     * @inheritDoc
     */
    public function line(string $string)
    {
        echo sprintf("%s: %s\n", date('Y-m-d H:i:s'), $string);
    }

    /**
     * @inheritDoc
     */
    public function error($string, $verbosity = null)
    {
        $this->line($string);
    }

    /**
     * @param      $string
     * @param null $verbosity
     */
    public function warn($string, $verbosity = null)
    {
        $this->line($string);
    }

    /**
     * @param      $string
     * @param null $verbosity
     */
    public function info($string, $verbosity = null)
    {
        $this->line($string);
    }
}
