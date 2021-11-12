<?php


namespace App\Http\Controllers;


use App\Console\AutoImports;
use App\Console\HaveAccess;
use App\Console\StartImport;
use App\Console\VerifyJSON;
use App\Exceptions\ImportException;
use App\Http\Request\AutoUploadRequest;
use Log;

/**
 *
 */
class AutoUploadController extends Controller
{
    use HaveAccess, AutoImports, VerifyJSON, StartImport;

    /**
     *
     */
    public function index(AutoUploadRequest $request)
    {
        $access = $this->haveAccess();
        if (false === $access) {
            throw new ImportException('Could not connect to your local Firefly III instance.');
        }

        $json = $request->file('json');
        $csv  = $request->file('csv');

        try {
            $this->importUpload($csv->getPathname(), $json->getPathname());
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
