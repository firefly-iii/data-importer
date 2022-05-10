<?php
/*
 * UploadController.php
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

namespace App\Http\Controllers\Import;


use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\UploadControllerMiddleware;
use App\Services\CSV\Configuration\ConfigFileProcessor;
use App\Services\Session\Constants;
use App\Services\Shared\Upload\UploadProcessor;
use App\Services\Storage\StorageService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\View\View;
use League\Csv\Exception;
use League\Csv\Reader;

/**
 * Class UploadController
 */
class UploadController extends Controller
{
    private array $processedImportables;
    private array $processedConfigurations;

    /**
     * UploadController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Upload files');
        $this->middleware(UploadControllerMiddleware::class);
        $this->processedImportables    = [];
        $this->processedConfigurations = [];
    }

    /**
     * @return Factory|View
     */
    public function index(Request $request)
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $mainTitle = 'Upload your file(s)';
        $subTitle  = 'Start page and instructions';
        $flow      = $request->cookie(Constants::FLOW_COOKIE);


        // get existing configs.
        $disk = Storage::disk('configurations');
        app('log')->debug(
            sprintf(
                'Going to check directory for config files: %s',
                config('filesystems.disks.configurations.root'),
            )
        );
        $all = $disk->files();

        // remove files from list
        $list    = [];
        $ignored = config('importer.ignored_files');
        foreach ($all as $entry) {
            if (!in_array($entry, $ignored, true)) {
                $list[] = $entry;
            }
        }

        app('log')->debug('List of files:', $list);

        return view('import.003-upload.index', compact('mainTitle', 'subTitle', 'list', 'flow'));
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse|Redirector
     * @throws ImporterErrorException
     */
    public function upload(Request $request)
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));

        $importableFiles            = $request->file('importable_file');
        $configFiles                = $request->file('config_file');
        $this->processedImportables = [];
        $flow                       = $request->cookie(Constants::FLOW_COOKIE);
        $errors                     = new MessageBag;
        $importCount                = null === $importableFiles ? 0 : count($importableFiles);
        $configCount                = null === $configFiles ? 0 : count($configFiles);
        $oneConfig                  = '1' === $request->get('one_config');

        // basic sanity checks:
        // 1. can only upload zero or one config
        if ($oneConfig && $configCount > 1) {
            // return with error.
            $errors->add('config_file', 'If you select that one configuration is enough, please do not upload multiple files.');

            return redirect(route('003-upload.index'))->withErrors($errors);
        }

        // 2. if more than one file uploaded, nr of config files uploaded must be equal.
        if (!$oneConfig && $configCount !== 0 && $importCount !== $configCount && 'file' === $flow) {
            // return with error.
            $errors->add('importable_file', 'Please upload an equal number of importable files and configuration files.');
            $errors->add('config_file', 'Please upload an equal number of importable files and configuration files.');

            return redirect(route('003-upload.index'))->withErrors($errors);
        }
        // 3. if not uploaded anything, return:
        if (0 === $importCount && 'file' === $flow) {
            // return with error.
            $errors->add('importable_file', 'Please upload something.');

            return redirect(route('003-upload.index'))->withErrors($errors);
        }

        /** @var UploadProcessor $processor */
        $processor = app(UploadProcessor::class);
        $processor->setContent($importableFiles, $configFiles);

        // collect array with upload info:
        $uploaded = $processor->getUploads($oneConfig);

        var_dump($uploaded);
        exit;

        $this->processedImportables    = $processor->getImportables();
        $this->processedConfigurations = $processor->getConfigurations();

        $processor->validateFiles($oneConfig);

        $errors                        = $processor->getErrors();

        // maybe the user selected a config file from the dropdown.
        $this->processSelection((string) $request->get('existing_config'));

        if ($errors->count() > 0) {
            return redirect(route('003-upload.index'))->withErrors($errors);
        }
        // error for Spectre and Nordigen
        if (count($this->processedConfigurations) > 1 && 'file' !== $flow) {
            $errors->add('config_file', 'This routine cannot handle more than 1 configuration.');
            return redirect(route('003-upload.index'))->withErrors($errors);
        }

        if ('nordigen' === $flow) {
            if (1 === count($this->processedConfigurations)) {
                session()->put(Constants::UPLOAD_CONFIG_FILE, $this->processedConfigurations[0]);
            }
            // redirect to country + bank selector
            session()->put(Constants::HAS_UPLOAD, true);
            return redirect(route('009-selection.index'));
        }

        if ('spectre' === $flow) {
            if (1 === count($this->processedConfigurations)) {
                session()->put(Constants::UPLOAD_CONFIG_FILE, $this->processedConfigurations[0]);
            }
            // redirect to spectre
            session()->put(Constants::HAS_UPLOAD, true);
            return redirect(route('011-connections.index'));
        }
        session()->put(Constants::CONFIG_FILE_PATHS, $this->processedConfigurations);
        session()->put(Constants::IMPORT_FILE_PATHS, $this->processedImportables);

        return redirect(route('004-configure.index'));
    }

    /**
     * TODO method needs to be file agnostic.
     * @return MessageBag
     */
    private function processCsvFile(string $flow, MessageBag $errors, UploadedFile|null $file): MessageBag
    {
        if (null === $file && 'file' === $flow) {
            $errors->add('importable_file', 'No file was uploaded.');
            return $errors;
        }
        if ('file' === $flow) {
            $errorNumber = $file->getError();
            if (0 !== $errorNumber) {
                $errors->add('importable_file', $this->getError($errorNumber));
            }


            // upload the file to a temp directory and use it from there.
            if (0 === $errorNumber) {
                $content = file_get_contents($file->getPathname());

                // https://stackoverflow.com/questions/11066857/detect-eol-type-using-php
                // because apparently there are banks that use "\r" as newline. Looking at the morons of KBC Bank, Belgium.
                // This one is for you: 🤦‍♀️
                $eol = $this->detectEOL($content);
                if ("\r" === $eol) {
                    app('log')->error('You bank is dumb. Tell them to fix their CSV files.');
                    $content = str_replace("\r", "\n", $content);
                }

                $fileName = StorageService::storeContent($content);
                session()->put(Constants::UPLOAD_CSV_FILE, $fileName);
                session()->put(Constants::HAS_UPLOAD, true);
            }
        }
        return $errors;
    }

    /**
     * @param int $error
     *
     * @return string
     */
    private function getError(int $error): string
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $errors = [
            UPLOAD_ERR_OK         => 'There is no error, the file uploaded with success.',
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk. Introduced in PHP 5.1.0.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
        ];

        return $errors[$error] ?? 'Unknown error';
    }

    /**
     * @param string $string
     *
     * @return string
     */
    private function detectEOL(string $string): string
    {
        $eols     = [
            '\n\r' => "\n\r",  // 0x0A - 0x0D - acorn BBC
            '\r\n' => "\r\n",  // 0x0D - 0x0A - Windows, DOS OS/2
            '\n'   => "\n",    // 0x0A -      - Unix, OSX
            '\r'   => "\r",    // 0x0D -      - Apple ][, TRS80
        ];
        $curCount = 0;
        $curEol   = '';
        foreach ($eols as $eolKey => $eol) {
            $count = substr_count($string, $eol);
            app('log')->debug(sprintf('Counted %dx "%s" EOL in upload.', $count, $eolKey));
            if ($count > $curCount) {
                $curCount = $count;
                $curEol   = $eol;
                app('log')->debug(sprintf('Conclusion: "%s" is the EOL in this file.', $eolKey));
            }
        }

        return $curEol;
    }

    /**
     * @param MessageBag        $errors
     * @param UploadedFile|null $file
     * @return MessageBag
     * @throws ImporterErrorException
     */
    private function processConfigFile(MessageBag $errors, UploadedFile|null $file): MessageBag
    {
        if (count($errors) > 0 || null === $file) {
            return $errors;
        }
        app('log')->debug('Config file is present.');
        $errorNumber = $file->getError();
        if (0 !== $errorNumber) {
            $errors->add('config_file', $errorNumber);
        }
        // upload the file to a temp directory and use it from there.
        if (0 === $errorNumber) {
            app('log')->debug('Config file uploaded.');
            $configFileName = StorageService::storeContent(file_get_contents($file->getPathname()));

            session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);

            // process the config file
            $success = false;
            try {
                $configuration = ConfigFileProcessor::convertConfigFile($configFileName);
                session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
                $success = true;
            } catch (ImporterErrorException $e) {
                $errors->add('config_file', $e->getMessage());
            }
            // if conversion of the config file was a success, store the new version again:
            if (true === $success) {
                $configuration->updateDateRange();
                $configFileName = StorageService::storeContent(json_encode($configuration->toArray(), JSON_PRETTY_PRINT));
                session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);
            }

        }
        return $errors;
    }

    /**
     * @param string $selection
     */
    private function processSelection(string $selection): void
    {
        if ('' === $selection) {
            return;
        }
        app('log')->debug('User selected a config file from the store.');
        $disk       = Storage::disk('configurations');
        $uploadDisk = Storage::disk('uploads');
        if ($disk->has($selection)) {
            try {
                $content = $disk->get($selection);
                $file    = StorageService::storeContent($content);
            } catch (FileNotFoundException|ImporterErrorException $e) {
                app('log')->warning(sprintf('Could not save pre-selected config: %s', $e->getMessage()));
                return;
            }
            $this->processedConfigurations[] = sprintf('%s/%s', storage_path('uploads'), $file);
        }
    }

    /**
     * @param array|null $importableFiles
     * @return MessageBag
     */
    private function processImportableFiles(array|null $importableFiles): MessageBag
    {
        $errors = new MessageBag;
        if (null === $importableFiles) {
            $errors->add('importable_file', 'No files were uploaded.');
            return $errors;
        }

        /** @var UploadedFile $file */
        foreach ($importableFiles as $file) {
            $errors = $this->processImportableFile($file);
            if ($errors->count() > 0) {
                return $errors;
            }
        }
        return $errors;
    }

    /**
     * @param UploadedFile $file
     * @return MessageBag
     * @throws ImporterErrorException
     */
    private function processImportableFile(UploadedFile $file): MessageBag
    {
        $errors      = new MessageBag;
        $errorNumber = $file->getError();

        if (0 !== $errorNumber) {
            $errors->add('importable_file', $this->getError($errorNumber));
            return $errors;
        }


        $type = $this->detectType($file->getPathname());

        // if is a zip,

        $content = file_get_contents($file->getPathname());


        if (false === $content) {
            $errors->add('importable_file', 'Could not read uploaded file from drive.');
            return $errors;
        }

        // https://stackoverflow.com/questions/11066857/detect-eol-type-using-php
        // because apparently there are banks that use "\r" as newline. Looking at the morons of KBC Bank, Belgium.
        // This one is for you: 🤦‍♀️
        if ('csv' === $type) {
            $eol = $this->detectEOL($content);
            if ("\r" === $eol) {
                app('log')->error('You bank is dumb. Tell them to fix their CSV files.');
                $content = str_replace("\r", "\n", $content);
            }
        }

        $fileName                     = StorageService::storeContent($content);
        $array                        = [
            'upload'  => $file->getPathname(),
            'storage' => $fileName,
            'type'    => $type,
        ];
        $this->processedImportables[] = $array;

        return $errors;
    }

    /**
     * @param string $path
     * @return string
     */
    private function detectType(string $path): string
    {
        if (!file_exists($path)) {
            return 'not-existing';
        }

        // is a ZIP file?
        $pointer = fopen($path, 'r', false);
        $blob    = fgets($pointer, 5);
        if (str_contains($blob, 'PK')) {
            $this->processZipFile($path);
            return 'zip';
        }

        // basic CSV check
        $reader   = Reader::createFromPath($path, 'r');
        $continue = true;
        try {
            $reader->setHeaderOffset(0);
        } catch (Exception $e) {
            app('log')->warning('Tried to process as CSV, but failed.');
            $continue = false;
        }
        if (true === $continue) {
            $header = $reader->getHeader();
            if (count($header) > 1) {
                return 'csv';
            }
        }

        // basic JSON test:
        $result = json_decode(file_get_contents($path), true);
        if (false !== $result) {
            return 'json';
        }
        // basic camt test. CAMT is XML.

        return 'unknown';
    }

    /**
     * Unzip ZIP file and process content.
     *
     * @param string $path
     * @return void
     */
    private function processZipFile(string $path): void
    {
    }

}
