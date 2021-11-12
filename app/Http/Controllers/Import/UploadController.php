<?php
declare(strict_types=1);
/**
 * UploadController.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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

namespace App\Http\Controllers\Import;


use App\Exceptions\ImportException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\UploadedFiles;
use App\Services\CSV\Configuration\ConfigFileProcessor;
use App\Services\Session\Constants;
use App\Services\Storage\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\MessageBag;
use Log;
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
        $this->middleware(UploadedFiles::class);
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse|Redirector
     */
    public function upload(Request $request)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $csvFile    = $request->file('csv_file');
        $configFile = $request->file('config_file');
        $errors     = new MessageBag;

        if (null === $csvFile) {
            $errors->add('csv_file', 'No file was uploaded.');

            return redirect(route('import.start'))->withErrors($errors);
        }
        $errorNumber = $csvFile->getError();
        if (0 !== $errorNumber) {
            $errors->add('csv_file', $this->getError($errorNumber));
        }

        // upload the file to a temp directory and use it from there.
        if (0 === $errorNumber) {
            $content = file_get_contents($csvFile->getPathname());

            // https://stackoverflow.com/questions/11066857/detect-eol-type-using-php
            // because apparantly there are banks that use "\r" as newline. Looking at the morons of KBC Bank, Belgium.
            // This one is for you: 🤦‍♀️
            $eol = $this->detectEOL($content);
            if ("\r" === $eol) {
                Log::error('You bank is dumb. Tell them to fix their CSV files.');
                $content = str_replace("\r", "\n", $content);
            }

            $csvFileName = StorageService::storeContent($content);
            session()->put(Constants::UPLOAD_CSV_FILE, $csvFileName);
            session()->put(Constants::HAS_UPLOAD, 'true');
        }

        // if present, and no errors, upload the config file and store it in the session.
        if (null !== $configFile) {
            Log::debug('Config file is present.');
            $errorNumber = $configFile->getError();
            if (0 !== $errorNumber) {
                $errors->add('config_file', $errorNumber);
            }
            // upload the file to a temp directory and use it from there.
            if (0 === $errorNumber) {
                Log::debug('Config file uploaded.');
                $configFileName = StorageService::storeContent(file_get_contents($configFile->getPathname()));

                session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);

                // process the config file
                try {
                    $configuration = ConfigFileProcessor::convertConfigFile($configFileName);
                    session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
                } catch (ImportException $e) {
                    $errors->add('config_file', $e->getMessage());
                }
            }
        }
        // if no uploaded config file, read and use the submitted existing file, if any.
        $existingFile = (string) $request->get('existing_config');

        if (null === $configFile && '' !== $existingFile) {
            Log::debug('User selected a config file from the store.');
            $disk           = Storage::disk('configurations');
            $configFileName = StorageService::storeContent($disk->get($existingFile));

            session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);

            // process the config file
            try {
                $configuration = ConfigFileProcessor::convertConfigFile($configFileName);
                session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
            } catch (ImportException $e) {
                $errors->add('config_file', $e->getMessage());
            }
        }

        if ($errors->count() > 0) {
            return redirect(route('import.start'))->withErrors($errors);
        }

        return redirect(route('import.configure.index'));
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
            Log::debug(sprintf('Counted %dx "%s" EOL in upload.', $count, $eolKey));
            if ($count > $curCount) {
                $curCount = $count;
                $curEol   = $eol;
                Log::debug(sprintf('Conclusion: "%s" is the EOL in this file.', $eolKey));
            }
        }

        return $curEol;
    }

}
