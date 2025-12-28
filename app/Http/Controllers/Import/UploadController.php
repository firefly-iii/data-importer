<?php

/*
 * UploadController.php
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

namespace App\Http\Controllers\Import;

use App\Console\VerifyJSON;
use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\File\FileContentSherlock;
use App\Services\SimpleFIN\Validation\NewJobDataCollector as SimpleFINNewJobDataCollector;
use App\Services\Sophtron\Validation\NewJobDataCollector as SophtronNewJobDataCollector;
use App\Support\Http\Upload\CollectsSettings;
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
use Storage;

/**
 * Class UploadController
 */
class UploadController extends Controller
{
    use CollectsSettings;
    use VerifyJSON;

    private string              $configFileContent     = '';
    private string              $configFileName;
    private string              $contentType;
    private string              $importableFileContent = '';
    private ImportJobRepository $repository;

    /**
     * UploadController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Upload files');
        // This variable is used to make sure the configuration object also knows the file type.
        $this->contentType    = 'unknown';
        $this->configFileName = '';
        $this->repository     = new ImportJobRepository();
    }

    /**
     * @return Factory|View
     */
    public function index(Request $request, string $flow)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $mainTitle = 'Upload your file(s)';
        $subTitle  = 'Start page and instructions';
        $settings  = [
            'simplefin' => $this->getSimpleFINSettings(),
        ];
        $list      = $this->getConfigurations();

        return view('import.003-upload.index', compact('mainTitle', 'subTitle', 'list', 'flow', 'settings'));
    }

    private function getConfigurations(): array
    {
        // get existing configurations.
        $disk = Storage::disk('configurations');
        Log::debug(sprintf('Going to check directory for config files: %s', config('filesystems.disks.configurations.root')));
        $all = $disk->files();

        // remove files from list
        $list    = [];
        $ignored = config('importer.ignored_files');
        foreach ($all as $entry) {
            if (!in_array($entry, $ignored, true)) {
                $list[] = $entry;
            }
        }
        Log::debug('List of files:', $list);

        return $list;
    }

    /**
     * @return Redirector|RedirectResponse
     *
     * @throws FileNotFoundException
     * @throws FilesystemException
     * @throws ImporterErrorException
     */
    public function upload(Request $request, string $flow)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        // need to process two possible file uploads:
        $importedFile = $request->file('importable_file');
        $configFile   = $request->file('config_file');
        $errors       = new MessageBag();

        // process uploaded file (if present)
        $errors = $this->processUploadedFile($flow, $errors, $importedFile);
        // content of importable file is now in $this->importableFileContent

        // process config file (if present)
        if (0 === count($errors) && null !== $configFile) {
            $errors = $this->processConfigFile($errors, $configFile);
        }
        // at this point the config (unprocessed) is in $this->configFileContent

        // process pre-selected file (if present):
        $errors = $this->processSelection($errors, (string)$request->get('existing_config'), $configFile);
        // the config in $this->configFileContent may now be overruled.

        // stop here if any errors:
        if ($errors->count() > 0) {
            return redirect(route('new-import.index', [$flow]))->withErrors($errors)->withInput();
        }
        // at this point, create a new import job. With raw content of the config + importable file.
        $importJob = $this->repository->create();
        $importJob = $this->repository->setFlow($importJob, $flow);
        $importJob = $this->repository->setConfigurationString($importJob, $this->configFileContent);
        $importJob = $this->repository->setImportableFileString($importJob, $this->importableFileContent);
        $importJob = $this->repository->markAs($importJob, 'contains_content');

        // FIXME: this little routine belongs in a function or a helper.
        // FIXME: it is duplicated
        // at this point, also parse and process the uploaded configuration file string.
        $configuration = Configuration::make();
        if ('' !== $this->configFileContent && null === $importJob->getConfiguration()) {
            $configuration = Configuration::fromArray(json_decode($this->configFileContent, true));
        }
        if (null !== $importJob->getConfiguration()) {
            $configuration = $importJob->getConfiguration();
        }
        $configuration->setFlow($importJob->getFlow());
        $importJob->setConfiguration($configuration);
        $this->repository->saveToDisk($importJob);


        // do validation for all configurations.
        switch ($flow) {
            default:
                throw new ImporterErrorException(sprintf('The data importer cannot deal with workflow "%s".', $flow));

            case 'file':
                Log::debug('No extra steps.');

                break;

            case 'simplefin':
                $collector = new SimpleFINNewJobDataCollector();
                $collector->setImportJob($importJob);
                $collector->useDemo    = $request->boolean('use_demo');
                $collector->setupToken = (string)$request->get('simplefin_token');
                $errors                = $collector->validate();
                $importJob             = $collector->getImportJob();
                $this->repository->saveToDisk($importJob);
                break;
            case 'sophtron':
                // only download when import job had no institution selected.

                $collector = new SophtronNewJobDataCollector();
                $collector->setImportJob($importJob);
                $collector->downloadInstitutions();
                $importJob = $collector->getImportJob();
                $this->repository->saveToDisk($importJob);
                exit;
                break;

            case 'nordigen':
                Log::debug('No extra steps for Nordigen.');

                break;

            case 'lunchflow':
                Log::debug('No extra steps for Lunch Flow.');

                break;
            //            case 'lunchflow':
            //                return $this->processLunchFlow($configuration);
            //
            //            case 'spectre':
            //                return $this->processSpectreUpload($configuration);
        }

        // stop again if any errors:
        if ($errors->count() > 0) {
            return redirect(route('new-import.index', [$flow]))->withErrors($errors)->withInput();
        }

        // redirect to configuration controller.
        return redirect()->route('configure-import.index', [$importJob->identifier]);

    }

    /**
     * @throws FilesystemException
     * @throws ImporterErrorException
     */
    private function processUploadedFile(string $flow, MessageBag $errors, ?UploadedFile $file): MessageBag
    {
        // add errors if not "file" flow, otherwise don't care about it.
        if (!$file instanceof UploadedFile && 'file' === $flow) {
            $errors->add('importable_file', 'No file was uploaded.');

            return $errors;
        }
        if ('file' !== $flow) {
            return $errors;
        }

        $errorNumber = $file->getError();
        if (0 !== $errorNumber) {
            $errors->add('importable_file', $this->getError($errorNumber));

            return $errors;
        }

        // upload the file to a temp directory and use it from there.
        $detector          = new FileContentSherlock();
        $this->contentType = $detector->detectContentType($file->getPathname());
        $content           = null;

        switch ($this->contentType) {
            case 'csv':
                $content = (string)file_get_contents($file->getPathname());

                // https://stackoverflow.com/questions/11066857/detect-eol-type-using-php
                // because apparently there are banks that use "\r" as newline. Looking at the morons of KBC Bank, Belgium.
                // This one is for you: 🤦‍♀️
                $eol = $this->detectEOL($content);
                if ("\r" === $eol) {
                    Log::error('Your bank is dumb. Tell them to fix their CSV files.');
                    $content = str_replace("\r", "\n", $content);
                }

                break;

            case 'camt':
                $content = (string)file_get_contents($file->getPathname());

                break;

            default:
                $errors->add('importable_file', sprintf('The file type of your upload is "%s". This file type is not supported. Please check the logs, and start over. Sorry about this.', $this->contentType));
        }
        if (null !== $content && '' !== $content) {
            $this->importableFileContent = $content;
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
                     '\r\n' => "\r\n", // 0x0D - 0x0A - Windows, DOS OS/2
                     '\n'   => "\n", // 0x0A -      - Unix, OSX
                     '\r'   => "\r", // 0x0D -      - Apple ][, TRS80
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
            $errors->add('config_file', (string)$errorNumber);

            return $errors;
        }

        // upload the file to a temp directory and use it from there.
        Log::debug('Config file uploaded.');
        $path       = $file->getPathname();
        $validation = $this->verifyJSON($path);
        if (false === $validation) {
            $errors->add('config_file', $this->errorMessage);

            return $errors;
        }

        $this->configFileContent = (string)file_get_contents($path);

        return $errors;
    }

    /**
     * @throws ImporterErrorException
     */
    private function processSelection(MessageBag $errors, string $selection, ?UploadedFile $file): MessageBag
    {
        if (!$file instanceof UploadedFile && '' !== $selection) {
            Log::debug('User selected a config file from the store.');
            $disk                    = Storage::disk('configurations');
            $content                 = (string)$disk->get($selection);
            $this->configFileContent = $content;
        }

        return $errors;
    }
}
