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
use App\Services\Shared\File\FileContentSherlock;
use App\Services\SimpleFIN\SimpleFINService;
use App\Services\Storage\StorageService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Illuminate\View\View;
use League\Flysystem\FilesystemException;

/**
 * Class UploadController
 */
class UploadController extends Controller
{
    private string $configFileName;
    private string $contentType;

    /**
     * UploadController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Upload files');
        $this->middleware(UploadControllerMiddleware::class);
        // This variable is used to make sure the configuration object also knows the file type.
        $this->contentType    = 'unknown';
        $this->configFileName = '';
    }

    /**
     * @return Factory|View
     */
    public function index(Request $request)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $mainTitle = 'Upload your file(s)';
        $subTitle  = 'Start page and instructions';
        $flow      = $request->cookie(Constants::FLOW_COOKIE);

        // get existing configs.
        $disk      = \Storage::disk('configurations');
        Log::debug(sprintf('Going to check directory for config files: %s', config('filesystems.disks.configurations.root')));
        $all       = $disk->files();

        // remove files from list
        $list      = [];
        $ignored   = config('importer.ignored_files');
        foreach ($all as $entry) {
            if (!in_array($entry, $ignored, true)) {
                $list[] = $entry;
            }
        }

        Log::debug('List of files:', $list);

        return view('import.003-upload.index', compact('mainTitle', 'subTitle', 'list', 'flow'));
    }

    /**
     * @return Redirector|RedirectResponse
     *
     * @throws FileNotFoundException
     * @throws FilesystemException
     * @throws ImporterErrorException
     */
    public function upload(Request $request)
    {
        Log::debug('UploadController::upload() INVOKED'); // Unique entry marker
        Log::debug(sprintf('Now at %s', __METHOD__));
        Log::debug('UploadController::upload() - Request All:', $request->all());
        $importedFile   = $request->file('importable_file');
        $configFile     = $request->file('config_file');
        $simpleFINtoken = $request->get('simplefin_token');
        $flow           = $request->cookie(Constants::FLOW_COOKIE);
        $errors         = new MessageBag();

        // process uploaded file (if present)
        $errors         = $this->processUploadedFile($flow, $errors, $importedFile);

        // process config file (if present)
        if (0 === count($errors) && null !== $configFile) {
            $errors = $this->processConfigFile($errors, $configFile);
        }

        // process pre-selected file (if present):
        $errors         = $this->processSelection($errors, (string)$request->get('existing_config'), $configFile);

        if ($errors->count() > 0) {
            return redirect(route('003-upload.index'))->withErrors($errors);
        }

        if ('simplefin' === $flow) {
            return $this->handleSimpleFINFlow($request, new MessageBag());
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
     * @throws FilesystemException
     * @throws ImporterErrorException
     */
    private function processUploadedFile(string $flow, MessageBag $errors, ?UploadedFile $file): MessageBag
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
                $detector          = new FileContentSherlock();
                $this->contentType = $detector->detectContentType($file->getPathname());
                $content           = '';
                if ('csv' === $this->contentType) {
                    $content = (string) file_get_contents($file->getPathname());

                    // https://stackoverflow.com/questions/11066857/detect-eol-type-using-php
                    // because apparently there are banks that use "\r" as newline. Looking at the morons of KBC Bank, Belgium.
                    // This one is for you: 🤦‍♀️
                    $eol     = $this->detectEOL($content);
                    if ("\r" === $eol) {
                        Log::error('Your bank is dumb. Tell them to fix their CSV files.');
                        $content = str_replace("\r", "\n", $content);
                    }
                }

                if ('camt' === $this->contentType) {
                    $content = (string) file_get_contents($file->getPathname());
                }
                $fileName          = StorageService::storeContent($content);
                session()->put(Constants::UPLOAD_DATA_FILE, $fileName);
                session()->put(Constants::HAS_UPLOAD, true);
            }
        }

        return $errors;
    }

    private function getError(int $error): string
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $errors = [UPLOAD_ERR_OK => 'There is no error, the file uploaded with success.', UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.', UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.', UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.', UPLOAD_ERR_NO_FILE => 'No file was uploaded.', UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.', UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk. Introduced in PHP 5.1.0.', UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'];

        return $errors[$error] ?? 'Unknown error';
    }

    private function detectEOL(string $string): string
    {
        $eols     = ['\n\r' => "\n\r", // 0x0A - 0x0D - acorn BBC
            '\r\n'          => "\r\n", // 0x0D - 0x0A - Windows, DOS OS/2
            '\n'            => "\n", // 0x0A -      - Unix, OSX
            '\r'            => "\r", // 0x0D -      - Apple ][, TRS80
        ];
        $curCount = 0;
        $curEol   = '';
        foreach ($eols as $eolKey => $eol) {
            $count = substr_count($string, $eol);
            Log::debug(sprintf('Counted %dx "%s" EOL in upload.', $count, $eolKey));
            if ($count > $curCount) {
                $curCount = $count;
                $curEol   = $eol;
                Log::debug(sprintf('Conclusion: "%s" is the EOL in this file.', $eolKey));
            }
        }

        return $curEol;
    }

    /**
     * @throws ImporterErrorException
     */
    private function processConfigFile(MessageBag $errors, UploadedFile $file): MessageBag
    {
        Log::debug('Config file is present.');
        $errorNumber = $file->getError();
        if (0 !== $errorNumber) {
            $errors->add('config_file', (string) $errorNumber);
        }
        // upload the file to a temp directory and use it from there.
        if (0 === $errorNumber) {
            Log::debug('Config file uploaded.');
            $this->configFileName = StorageService::storeContent((string) file_get_contents($file->getPathname()));

            session()->put(Constants::UPLOAD_CONFIG_FILE, $this->configFileName);

            // process the config file
            $success              = false;
            $configuration        = null;

            try {
                $configuration = ConfigFileProcessor::convertConfigFile($this->configFileName);
                $configuration->setContentType($this->contentType);
                session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
                $success       = true;
            } catch (ImporterErrorException $e) {
                $errors->add('config_file', $e->getMessage());
            }
            // if conversion of the config file was a success, store the new version again:
            if (true === $success) {
                $configuration->updateDateRange();
                $this->configFileName = StorageService::storeContent((string) json_encode($configuration->toArray(), JSON_PRETTY_PRINT));
                session()->put(Constants::UPLOAD_CONFIG_FILE, $this->configFileName);
            }
        }

        return $errors;
    }

    /**
     * @throws ImporterErrorException
     */
    private function processSelection(MessageBag $errors, string $selection, ?UploadedFile $file): MessageBag
    {
        if (null === $file && '' !== $selection) {
            Log::debug('User selected a config file from the store.');
            $disk           = \Storage::disk('configurations');
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

    /**
     * Handle SimpleFIN flow integration
     */
    private function handleSimpleFINFlow(Request $request, MessageBag $errors): RedirectResponse
    {
        Log::debug('UploadController::handleSimpleFINFlow() INVOKED'); // Unique entry marker
        Log::debug('Processing SimpleFIN flow in unified controller');
        Log::debug('handleSimpleFINFlow() - Request All:', $request->all());
        Log::debug('handleSimpleFINFlow() - Raw use_demo input:', [$request->input('use_demo')]);

        $simpleFINToken = (string)$request->get('simplefin_token');
        $bridgeUrl      = (string)$request->get('simplefin_bridge_url');
        $isDemo         = $request->boolean('use_demo');
        Log::debug('handleSimpleFINFlow() - Evaluated $isDemo:', [$isDemo]);
        Log::debug('handleSimpleFINFlow() - Bridge URL:', [$bridgeUrl]);


        if ($isDemo) {
            $simpleFINToken = (string)config('importer.simplefin.demo_token');
            $bridgeUrl      = 'https://sfin.bridge.which.is'; // Demo mode uses known working Origin
        }
        if (!$isDemo) {
            if ('' === $simpleFINToken) {
                $errors->add('simplefin_token', 'SimpleFIN token is required.');
            }
            if ('' === $bridgeUrl) {
                $errors->add('simplefin_bridge_url', 'Bridge URL is required for CORS Origin header.');
            }
            if ('' !== $bridgeUrl && false === filter_var($bridgeUrl, FILTER_VALIDATE_URL)) {
                $errors->add('simplefin_bridge_url', 'Bridge URL must be a valid URL.');
            }
        }

        if ($errors->count() > 0) {
            return redirect(route('003-upload.index'))->withErrors($errors);
        }

        // Store bridge URL in session BEFORE SimpleFIN service call (service needs it for Origin header)
        session()->put(Constants::SIMPLEFIN_BRIDGE_URL, $bridgeUrl);

        try {
            $simpleFINService = app(SimpleFINService::class);
            // Use demo URL for demo mode, otherwise use empty string for claim URL token processing
            $apiUrl           = $isDemo ? config('importer.simplefin.demo_url') : '';
            $accountsData     = $simpleFINService->fetchAccountsAndInitialData($simpleFINToken, $apiUrl);

            // Store SimpleFIN data in session for configuration step
            session()->put(Constants::SIMPLEFIN_TOKEN, $simpleFINToken);
            session()->put(Constants::SIMPLEFIN_ACCOUNTS_DATA, $accountsData);
            session()->put(Constants::SIMPLEFIN_IS_DEMO, $isDemo);
            session()->put(Constants::HAS_UPLOAD, true);

            Log::info('SimpleFIN connection established', ['account_count' => count($accountsData), 'is_demo' => $isDemo]);

            return redirect(route('004-configure.index'));
        } catch (ImporterErrorException $e) {
            Log::error('SimpleFIN connection failed', ['error' => $e->getMessage()]);
            $errors->add('connection', 'Failed to connect to SimpleFIN: '.$e->getMessage());

            return redirect(route('003-upload.index'))->withErrors($errors);
        }
    }
}
