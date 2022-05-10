<?php
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

use App\Services\Storage\StorageService;
use Illuminate\Support\MessageBag;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

/**
 * Class UploadProcessor
 */
class UploadProcessor
{
    private array      $configFiles;
    private MessageBag $errors;
    private array      $importableFiles;
    private array      $returnableFiles;
    private array      $returnableConfigurations;

    // original file names
    private array $importableNames;
    private array $configNames;

    public function __construct()
    {
        $this->importableFiles          = [];
        $this->configFiles              = [];
        $this->returnableFiles          = [];
        $this->returnableConfigurations = [];
        $this->importableNames          = [];
        $this->configNames              = [];
        $this->errors                   = new MessageBag;
    }

    /**
     * @return array
     */
    public function getConfigurations(): array
    {
        app('log')->debug('getConfigurations()');
        /** @var UploadedFile $file */
        foreach ($this->configFiles as $file) {
            $this->processUploadedConfig($file);
        }
        $this->returnableConfigurations = $this->removeDuplicates($this->returnableConfigurations);
        return $this->returnableConfigurations;
    }

    /**
     * @return MessageBag
     */
    public function getErrors(): MessageBag
    {
        return $this->errors;
    }

    /**
     * @return array
     */
    public function getImportables(): array
    {
        app('log')->debug('getImportables()');
        /** @var UploadedFile $file */
        foreach ($this->importableFiles as $file) {
            $this->processUploadedFile($file);
        }
        $this->returnableFiles = $this->removeDuplicates($this->returnableFiles);
        return $this->returnableFiles;
    }

    /**
     * @param UploadedFile $file
     * @return void
     */
    private function processUploadedFile(UploadedFile $file): void
    {
        app('log')->debug('processUploadedFile()');
        $errorNumber = $file->getError();
        if (0 !== $errorNumber) {
            $this->errors->add('importable_file', $this->getError($errorNumber));
            return;
        }
        $name = $file->getClientOriginalName();
        $path = $file->getPathname();

        $this->includeForSelection($path, $name);


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
     * @param string $path
     * @return string
     */
    private function detectType(string $path): string
    {
        app('log')->debug('detectType()');
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
     * @param string $path
     * @return void
     */
    private function processZipFile(string $path): void
    {
        app('log')->debug('processZipFile()');
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
     * Process the uploaded files and configuration files. Returns array with
     * both.
     *
     * @return array
     */
    public function getUploads(): array
    {
        $return = [];
        // first process all uploads (which are mandatory)
        if (0 === count($this->importableFiles) && 0 === count($this->configFiles)) {
            return [
                [
                    'configuration' => [
                        'original_name' => null,
                        'location'      => null,
                    ],
                    'importable'    => [
                        'original_name' => null,
                        'location'      => null,
                    ],
                ],
            ];
        }
        // "primary" source is the importable files.
        // first process them (and unpack zip files etc.
        /** @var UploadedFile $importableFile */
        foreach ($this->importableFiles as $importableFile) {
            $this->processUploadedFile($importableFile);
        }


        die('here we are');
    }

    /**
     * @param array|null $importableFiles
     * @param array|null $configFiles
     * @return void
     */
    public function setContent(?array $importableFiles, ?array $configFiles): void
    {
        if (is_array($importableFiles)) {
            $this->importableFiles = $importableFiles;
        }
        if (is_array($configFiles)) {
            $this->configFiles = $configFiles;
        }
    }

    /**
     * @param string $path
     * @param string $name
     * @return void
     */
    private function includeForSelection(string $path, string $name): void
    {
        app('log')->debug(sprintf('includeForSelection("%s", "%s")', $path, $name));
        $type = $this->detectType($path);

        app('log')->debug(sprintf('File type of "%s" ("%s") is "%s"', $path, $name, $type));

        switch ($type) {
            default:
                app('log')->error(sprintf('Cannot handle unknown file type "%s" (file "%s"). Will ignore file.', $type, $name));
                break;
            case 'zip':
                if (config('importer.support_zip_files')) {
                    $this->processZipFile($path);
                }
                if (!config('importer.support_zip_files')) {
                    app('log')->warning(sprintf('Will ignore ZIP file "%s".', $name));
                }
                break;
            case 'json':
                // add uploaded JSON files to the configuration array
                app('log')->info(sprintf('Will include file "%s" (in config array).', $name));

                // use storage service to save the file:
                $result                           = StorageService::storeContent(file_get_contents($path));
                $this->returnableConfigurations[] =
                    [
                        'original_name'    => $name,
                        'storage_location' => $result,
                    ];
                // technically this extra array is no longer necessary. But it is easier.
                $this->configNames[] = $name;
                break;
            // can later be extended to split between XML, CAMT, etc.
            case 'text':
            case 'xml':
                app('log')->info(sprintf('Will include file "%s".', $name));
                $result                  = StorageService::storeContent(file_get_contents($path));
                $this->returnableFiles[] = [
                    'original_name'    => $name,
                    'storage_location' => $result,
                ];
                // technically this extra array is no longer necessary. But it is easier.
                $this->importableNames[] = $name;
                break;
        }
    }

    /**
     * @param UploadedFile $file
     * @return void
     */
    private function processUploadedConfig(UploadedFile $file): void
    {
        app('log')->debug('processUploadedConfig()');
        $errorNumber = $file->getError();
        if (0 !== $errorNumber) {
            $this->errors->add('config_file', $this->getError($errorNumber));
            return;
        }
        $name = $file->getClientOriginalName();
        $path = $file->getPathname();
        app('log')->debug(sprintf('processUploadedConfig "%s" "%s"', $name, $path));

        $this->includeForSelection($path, $name);
    }

    /**
     * @param array $array
     * @return array
     */
    private function removeDuplicates(array $array): array
    {
        app('log')->debug('removeDuplicates()');
        $hashes = [];
        $return = [];
        foreach ($array as $file) {
            $hash = StorageService::hash($file['storage_location']);
            if (!in_array($hash, $hashes, true)) {
                // include in return.
                $return[] = $file;
                $hashes[] = $hash;
            }
        }
        return $return;
    }

    /**
     * @return array
     */
    public function getImportableNames(): array
    {
        return $this->importableNames;
    }

    /**
     * @return array
     */
    public function getConfigNames(): array
    {
        return $this->configNames;
    }

    /**
     * @return void
     */
    public function validateFiles(bool $singleConfig): void
    {

        app('log')->debug('validateFiles()');
        if (0 === count($this->configNames)) {
            // no config files, nothing to validate.
            return;
        }
        if (1 === count($this->configNames) && true === $singleConfig) {
            // a single config file applies to all, nothing to validate.
            return;
        }

        // check uploads first:
        /** @var string $name */
        foreach ($this->importableNames as $name) {
            app('log')->debug(sprintf('Check file "%s".', $name));
            $short = $this->removeExtension($name);
            app('log')->debug(sprintf('Short name is "%s".', $short));
            // need a similar file in the config array
            $found = false;
            /** @var string $configFile */
            foreach ($this->configNames as $configFile) {
                app('log')->debug(sprintf('Checking config file "%s"', $configFile));
                $configShort = $this->removeExtension($configFile);
                app('log')->debug(sprintf('Short name is "%s"', $configShort));
                if ($configShort === $short) {
                    $found = true;
                    app('log')->debug('Is the same!');
                }
                if ($configShort !== $short) {
                    app('log')->debug('Is not same!');
                }
            }
            if (false === $found) {
                $this->errors->add('importable_file', sprintf('File "%s" needs a configuration file called "%s.json"', $name, $short));
            }
        }
        unset($name, $found, $configShort, $short);
        // check configurations next:
        foreach ($this->configNames as $configName) {
            app('log')->debug(sprintf('Check config "%s".', $configName));
            $short = $this->removeExtension($configName);
            app('log')->debug(sprintf('Short name is "%s".', $short));
            // need a similar file in the config array
            $found = false;

            /** @var string $importName */
            foreach ($this->importableNames as $importName) {
                app('log')->debug(sprintf('Checking importable file "%s"', $importName));
                $importShort = $this->removeExtension($importName);
                app('log')->debug(sprintf('Short name is "%s"', $importShort));
                if ($importShort === $short) {
                    $found = true;
                    app('log')->debug('Is the same!');
                }
                if ($importShort !== $short) {
                    app('log')->debug('Is not same!');
                }
            }
            if (false === $found) {
                $this->errors->add('config_file', sprintf('Config file "%s" needs an importable file called "%s.*"', $configName, $short));
            }
        }
    }

    /**
     * @param string $name
     * @return string
     */
    private function removeExtension(string $name): string
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


}
