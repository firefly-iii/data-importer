<?php
declare(strict_types=1);
/*
 * UploadProcessor.php
 * Copyright (c) 2022 james@firefly-iii.org
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

namespace App\Services\Shared\Upload;

use App\Exceptions\ImporterErrorException;
use App\Services\Storage\StorageService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

/**
 * Class UploadProcessor
 */
class UploadProcessor
{
    private array      $combinations;
    private MessageBag $errors;
    private string     $existingConfiguration;
    private array      $processedConfigs;
    private array      $processedImportables;
    private bool       $singleConfiguration;
    private array      $uploadedConfigs;
    private array      $uploadedImportables;
    private string     $flow;

    // original file names

    public function __construct()
    {
        $this->uploadedConfigs       = [];
        $this->uploadedImportables   = [];
        $this->processedConfigs      = [];
        $this->processedImportables  = [];
        $this->combinations          = [];
        $this->existingConfiguration = '';
        $this->flow                  = 'file';
        $this->singleConfiguration   = false;
        $this->errors                = new MessageBag;
    }

    /**
     * @param string $flow
     */
    public function setFlow(string $flow): void
    {
        $this->flow = $flow;
    }

    /**
     * @return array
     */
    public function getCombinations(): array
    {
        return $this->combinations;
    }

    /**
     * @return MessageBag
     */
    public function getErrors(): MessageBag
    {
        return $this->errors;
    }

    /**
     * Process all uploaded files.
     * @return void
     * @throws ImporterErrorException
     */
    public function process(): void
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        /** @var UploadedFile $file */
        foreach ($this->uploadedImportables as $file) {
            $this->processUploadedFile($file);
        }
        /** @var UploadedFile $file */
        foreach ($this->uploadedConfigs as $file) {
            $this->processUploadedConfig($file);
        }
        // also process pre-selected config, if any:
        $this->processExistingConfig();


        // here we mix and match into a new array:
        $this->validateUploads();
        if (0 === $this->errors->count()) {
            $this->combine();
        }
    }

    /**
     * Process an upload. Could be a ZIP file, CSV file, anything. After a first sanity check
     * the uploaded (temp) name and the original file name are retrieved and forwarded to the next method.
     *
     * @param UploadedFile $file
     * @return void
     * @throws ImporterErrorException
     */
    private function processUploadedFile(UploadedFile $file): void
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $errorNumber  = $file->getError();
        $errorMessage = $this->getErrorMessage($errorNumber);
        if (0 !== $errorNumber) {
            app('log')->error(sprintf(sprintf('Detected error #%d (%s) with file upload.', $errorNumber, $errorMessage)));
            $this->errors->add('importable_file', $errorMessage);
            return;
        }
        $name = $file->getClientOriginalName();
        $path = $file->getPathname();

        $this->includeForSelection($path, $name);
    }

    /**
     * Returns a nice error message for uploads.
     * @param int $error
     *
     * @return string
     */
    private function getErrorMessage(int $error): string
    {
        app('log')->debug(sprintf('Now in %s(%d)', __METHOD__, $error));
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
     * This method checks the original file type and decides which processing steps must be taken.
     *
     * @param string $path
     * @param string $name
     * @return void
     * @throws ImporterErrorException
     */
    private function includeForSelection(string $path, string $name): void
    {
        app('log')->debug(sprintf('Now in %s("%s", "%s")', __METHOD__, $path, $name));
        $type = $this->detectFileType($path);

        app('log')->debug(sprintf('File type of "%s" ("%s") is "%s"', $path, $name, $type));

        switch ($type) {
            default:
                app('log')->error(sprintf('Cannot handle unknown file type "%s" (file "%s"). Will ignore file.', $type, $name));
                break;
            case 'zip':
                $this->processZipFile($path);
                break;
            case 'json':
                $this->processJsonFile($path, $name);
                break;
            // can later be extended to split between XML, CAMT, etc.
            case 'text':
            case 'xml':
                $this->processImportableFile($path, $name);
                break;
        }
    }

    /**
     * This method detects the file type of the uploaded file.
     *
     * @param string $path
     * @return string
     */
    private function detectFileType(string $path): string
    {
        app('log')->debug(sprintf('Now in %s("%s")', __METHOD__, $path));
        $fileType   = mime_content_type($path);
        $returnType = 'unknown';
        switch ($fileType) {
            case 'application/csv':
            case 'text/csv':
            case 'text/plain':
                // here we can always dive into the exact file content to make sure it's CSV.
                $returnType = 'text';
                break;
            case 'application/json':
                $returnType = 'json';
                break;
            case 'application/zip':
                $returnType = 'zip';
                break;
            case 'text/xml':
                // here we can always dive into the exact file content.
                $returnType = 'xml';
                break;
        }
        app('log')->debug(sprintf('Mime seems to be "%s", so return "%s".', $fileType, $returnType));

        return $returnType;
    }

    /**
     * This method will unpack a zip file and return the found files back to the "inclusion" method for further processing.
     *
     * @param string $path
     * @return void
     * @throws ImporterErrorException
     */
    private function processZipFile(string $path): void
    {
        app('log')->debug(sprintf('Now in %s("%s")', __METHOD__, $path));
        if (!config('importer.support_zip_files')) {
            app('log')->warning(sprintf('Will ignore ZIP file "%s".', $path));
            return;
        }
        $archive = new ZipArchive;
        $archive->open($path);
        app('log')->debug(sprintf('Unpacking zip file "%s"', $path));

        for ($index = 0; $index < $archive->count(); $index++) {
            $currentName = $archive->getNameIndex($index);
            app('log')->debug(sprintf('Found file "%s" in zip file.', $currentName));

            $content  = $archive->getFromIndex($index);
            $tempFile = tmpfile();
            $tempPath = stream_get_meta_data($tempFile)['uri'];
            fwrite($tempFile, $content);

            $this->includeForSelection($tempPath, $currentName);
        }
    }

    /**
     * This method saves the config file to the disk and stores the file name + original name in an array of processed
     * configuration files.
     *
     * @param string $path
     * @param string $name
     * @return void
     * @throws ImporterErrorException
     */
    private function processJsonFile(string $path, string $name): void
    {
        app('log')->debug(sprintf('Now in %s("%s", "%s")', __METHOD__, $path, $name));

        // use storage service to save the file:
        $result = StorageService::storeContent(file_get_contents($path));

        $this->processedConfigs[] =
            [
                'original_name'    => $name,
                'storage_location' => $result,
            ];
    }

    /**
     * This method saves the importable file to the disk and stores the file name + original name in an array of processed
     * importable files.
     *
     * @param string $path
     * @param string $name
     * @return void
     * @throws ImporterErrorException
     */
    private function processImportableFile(string $path, string $name): void
    {
        app('log')->debug(sprintf('Now in %s("%s", "%s")', __METHOD__, $path, $name));
        $result                       = StorageService::storeContent(file_get_contents($path));
        $this->processedImportables[] = [
            'original_name'    => $name,
            'storage_location' => $result,
        ];
    }

    /**
     * Process an uploaded configuration file. Could be anything at this point.
     *
     * @param UploadedFile $file
     * @return void
     * @throws ImporterErrorException
     */
    private function processUploadedConfig(UploadedFile $file): void
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $errorNumber  = $file->getError();
        $errorMessage = $this->getErrorMessage($errorNumber);
        if (0 !== $errorNumber) {
            app('log')->error(sprintf(sprintf('Detected error #%d (%s) with file upload.', $errorNumber, $errorMessage)));
            $this->errors->add('config_file', $errorMessage);
            return;
        }

        $name = $file->getClientOriginalName();
        $path = $file->getPathname();

        $this->includeForSelection($path, $name);
    }

    /**
     * This method processes the existing configuration file, if any, and "uploads" it so it can be processed as a normal file.
     *
     * @return void
     */
    private function processExistingConfig(): void
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        if ('' === $this->existingConfiguration) {
            return;
        }
        $name = $this->existingConfiguration;
        $disk = Storage::disk('configurations');
        if ($disk->exists($name)) {
            $content                  = $disk->get($name);
            $result                   = StorageService::storeContent($content);
            $this->processedConfigs[] = [
                'original_name'    => $name,
                'storage_location' => $result,
            ];
        }
    }

    /**
     * This method checks if all uploaded files also have a configuration file. It's not mandatory to have one.
     * @return void
     */
    public function validateUploads(): void
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        if (0 === count($this->processedConfigs)) {
            // no config files, nothing to validate.
            return;
        }
        if (1 === count($this->processedConfigs) && true === $this->singleConfiguration) {
            // a single config file applies to all, nothing to validate.
            return;
        }

        // reset errors
        $this->errors = new MessageBag;
        $this->validateImportables();
        $this->validateConfigurations();
    }

    /**
     * This method will validate all importable files.
     * @return void
     */
    private function validateImportables(): void
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        /** @var array $array */
        foreach ($this->processedImportables as $array) {
            $this->validateSingleImportable($array);
        }
    }

    /**
     * This method will see if a single importable has a configuration file counterpart. It will give an error when there is no such file.
     * Technically speaking, this is not necessary, but right now it's "all or nothing". Either ALL importable files have a configuration
     * or NO importable files have a configuration.
     *
     * @param array $file
     * @return void
     */
    private function validateSingleImportable(array $file): void
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $name  = $file['original_name'];
        $short = $this->removeFileExtension($name);

        // need a similar file in the config array, so loop them all:
        $found = false;

        /** @var array $configuration */
        foreach ($this->processedConfigs as $configuration) {
            $configName  = $configuration['original_name'];
            $configShort = $this->removeFileExtension($configName);
            $found       = $configShort === $short ? true : $found;
        }
        if (false === $found) {
            $this->errors->add('importable_file', sprintf('File "%s" needs a configuration file called "%s.json"', $name, $short));
        }
    }

    /**
     * Remove the extension from a file name. "a.csv" > "a".
     * @param string $name
     * @return string
     */
    private function removeFileExtension(string $name): string
    {
        $parts = explode('.', $name);
        if (1 === count($parts)) {
            return $name;
        }
        if (2 === count($parts)) {
            return $parts[0];
        }
        array_pop($parts);
        return implode('.', $parts);
    }

    /**
     * This method will validate all importable files.
     * @return void
     */
    private function validateConfigurations(): void
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        /** @var array $array */
        foreach ($this->processedConfigs as $array) {
            $this->validateSingleConfiguration($array);
        }
    }

    /**
     * Will check if the configuration has an importable counterpart. This is mandatory.
     * @param array $configuration
     * @return void
     */
    private function validateSingleConfiguration(array $configuration): void
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $name  = $configuration['original_name'];
        $short = $this->removeFileExtension($name);
        $found = false;

        /** @var array $importable */
        foreach ($this->processedImportables as $importable) {
            $importableName  = $importable['original_name'];
            $importableShort = $this->removeFileExtension($importableName);
            $found           = $importableShort === $short ? true : $found;
        }
        if (false === $found && 'file' === $this->flow) {
            $this->errors->add('config_file', sprintf('Importable file "%s" needs a config file called "%s.json"', $name, $short));
        }
    }

    /**
     * Creates a new array that includes a configuration file reference (or NULL) for each uploaded file.
     *
     * @return void
     */
    private function combine(): void
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $result = [];
        if ('file' === $this->flow) {
            /** @var array $importable */
            foreach ($this->processedImportables as $importable) {
                $configuration = $this->findConfigForCombination($importable['original_name']);
                $result[]      = [
                    'original_name'         => $importable['original_name'],
                    'storage_location'      => $importable['storage_location'],
                    'config_name'           => null === $configuration ? null : $configuration['original_name'],
                    'config_location'       => null === $configuration ? null : $configuration['storage_location'],
                    'conversion_identifier' => null,
                ];
            }

            $this->combinations = $result;
            return;
        }
        // if flow is not 'file', returning the first config file is enough:
        $array              = $this->processedConfigs;
        $configuration      = array_shift($array);
        $result[]           = [
            'original_name'         => null,
            'storage_location'      => null,
            'config_name'           => $configuration['original_name'] ?? null,
            'config_location'       => $configuration['storage_location'] ?? null,
            'conversion_identifier' => null,
        ];
        $this->combinations = $result;
    }

    /**
     * Loop the configurations we have and return the proper matching configuration for the given file name.
     * @param string $name
     * @return array|null
     */
    private function findConfigForCombination(string $name): ?array
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));

        // if single config, just return the first one.
        if ($this->singleConfiguration) {
            $array = $this->processedConfigs;
            return array_shift($array);
        }

        $short = $this->removeFileExtension($name);
        /** @var array $config */
        foreach ($this->processedConfigs as $config) {
            $configShort = $this->removeFileExtension($config['original_name']);
            if ($short === $configShort) {
                return $config;
            }
        }
        return null;
    }

    /**
     * This method sets the uploaded files to be processed.
     *
     * @param array|null $importableFiles
     * @param array|null $configFiles
     * @return void
     */
    public function setContent(?array $importableFiles, ?array $configFiles): void
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        if (is_array($importableFiles)) {
            $this->uploadedImportables = $importableFiles;
        }
        if (is_array($configFiles)) {
            $this->uploadedConfigs = $configFiles;
        }
    }

    /**
     * @param string $existingConfiguration
     */
    public function setExistingConfiguration(string $existingConfiguration): void
    {
        $this->existingConfiguration = $existingConfiguration;
    }

    /**
     * @param bool $singleConfiguration
     */
    public function setSingleConfiguration(bool $singleConfiguration): void
    {
        $this->singleConfiguration = $singleConfiguration;
    }

}
