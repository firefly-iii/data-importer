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
use App\Services\Storage\StorageService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Redirector;
use Illuminate\Support\MessageBag;
use Illuminate\View\View;
use Storage;

/**
 * Class UploadController
 */
class UploadController extends Controller
{
    /**
     * UploadController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Upload files');
        $this->middleware(UploadControllerMiddleware::class);
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
     * @throws FileNotFoundException
     */
    public function upload(Request $request)
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));

        $uploads         = $request->file('importable_file');
        $configFile      = $request->file('config_file');
        $flow            = $request->cookie(Constants::FLOW_COOKIE);
        $errors          = new MessageBag();
        $importableFiles = [];

        if ($uploads instanceof UploadedFile) {
            $importableFiles = [$uploads];
        }
        if (is_array($uploads)) {
            $importableFiles = $uploads;
        }

        // process uploaded file (if present)
        $errors = $this->processImportableUpload($flow, $errors, $importableFiles);

        // process config file (if present)
        $errors = $this->processConfigFile($errors, $configFile);

        // process pre-selected file (if present):
        $errors = $this->processSelection($errors, (string)$request->get('existing_config'), $configFile);


        if ($errors->count() > 0) {
            return redirect(route('003-upload.index'))->withErrors($errors);
        }

        if ('nordigen' === $flow) {
            // redirect to country + bank selector
            session()->put(Constants::HAS_UPLOAD, true);

            return redirect(route('009-selection.index'));
        }
        if ('spectre' === $flow) {
            // redirect to spectre
            session()->put(Constants::HAS_UPLOAD, true);

            return redirect(route('011-connections.index'));
        }

        return redirect(route('004-configure.index'));
    }

    /**
     * TODO method needs to be file agnostic.
     *
     * @return MessageBag
     */
    private function processImportableUpload(string $flow, MessageBag $errors, array $uploadedFiles): MessageBag
    {
        $files = [];
        /** @var UploadedFile $file */
        foreach ($uploadedFiles as $file) {
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
                    $originalName         = app('steam')->cleanStringAndNewlines($file->getClientOriginalName());
                    $files[$originalName] = StorageService::storeContent($content);

                }
            }
        }
        if (0 !== count($files)) {
            session()->put(Constants::UPLOADED_IMPORTS, $files);
            session()->put(Constants::HAS_UPLOAD, true);
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
     *
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
     * @param MessageBag        $errors
     * @param string            $selection
     * @param UploadedFile|null $file
     *
     * @return MessageBag
     * @throws ImporterErrorException
     * @throws FileNotFoundException
     */
    private function processSelection(MessageBag $errors, string $selection, UploadedFile|null $file): MessageBag
    {
        if (null === $file && '' !== $selection) {
            app('log')->debug('User selected a config file from the store.');
            $disk           = Storage::disk('configurations');
            $configFileName = StorageService::storeContent($disk->get($selection));

            session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);

            // process the config file
            try {
                $configuration = ConfigFileProcessor::convertConfigFile($configFileName);
                session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
            } catch (ImporterErrorException $e) {
                $errors->add('config_file', $e->getMessage());
            }
        }

        return $errors;
    }
}
