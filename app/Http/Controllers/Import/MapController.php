<?php

/*
 * MapController.php
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
use App\Http\Middleware\MapControllerMiddleware;
use App\Services\CSV\Mapper\MapperInterface;
use App\Services\CSV\Mapper\MapperService;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Storage\StorageService;
use App\Support\Http\RestoresConfiguration;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Class MapController
 */
class MapController extends Controller
{
    use RestoresConfiguration;

    protected const DISK_NAME = 'jobs';

    /**
     * RoleController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        app('view')->share('pageTitle', 'Map data');
        $this->middleware(MapControllerMiddleware::class);
    }

    /**
     * @return Factory|RedirectResponse|View
     */
    public function index()
    {
        Log::debug('Now in mapController index');

        $mainTitle     = 'Map data';
        $subTitle      = 'Map values in file to actual data in Firefly III';
        $configuration = $this->restoreConfiguration();
        $data          = [];
        $roles         = [];

        if ('file' === $configuration->getFlow() && 'csv' === $configuration->getContentType()) {
            Log::debug('Get mapping data for CSV file');
            $roles = $configuration->getRoles();
            $data  = $this->getCSVMapInformation();
        }
        if ('file' === $configuration->getFlow() && 'camt' === $configuration->getContentType()) {
            Log::debug('Get mapping data for CAMT file');
            $roles = $configuration->getRoles();
            $data  = $this->getCamtMapInformation();
        }

        // nordigen, spectre, simplefin and others:
        if ('file' !== $configuration->getFlow()) {
            Log::debug('Get mapping data for GoCardless, Spectre, and SimpleFIN');
            $roles = [];
            $data  = $this->getImporterMapInformation();
        }

        // if nothing to map, just set mappable to true and go to the next step:
        if (0 === count($data)) {
            // set map config as complete.
            session()->put(Constants::MAPPING_COMPLETE_INDICATOR, true);

            // if "file", now ready for conversion
            if ('file' === $configuration->getFlow()) {
                Log::debug('Its a file, also set ready for conversion.');
                session()->put(Constants::READY_FOR_CONVERSION, true);
            }

            return redirect()->route('007-convert.index');
        }

        return view('import.006-mapping.index', compact('mainTitle', 'subTitle', 'roles', 'data'));
    }

    /**
     * Return the map data necessary for the importable file mapping based on some weird helpers.
     * TODO needs refactoring and proper splitting into helpers.
     * TODO needs renaming or specific CAMT counterpart.
     */
    private function getCSVMapInformation(): array
    {
        $configuration   = $this->restoreConfiguration();
        $roles           = $configuration->getRoles();
        $existingMapping = $configuration->getMapping();
        $doMapping       = $configuration->getDoMapping();
        $data            = [];

        foreach ($roles as $index => $role) {
            $info                 = config('csv.import_roles')[$role] ?? null;
            $mappable             = $info['mappable'] ?? false;
            if (null === $info) {
                continue;
            }
            if (false === $mappable) {
                continue;
            }
            $mapColumn            = $doMapping[$index] ?? false;
            if (false === $mapColumn) {
                continue;
            }
            Log::debug(sprintf('Mappable role is "%s"', $role));

            $info['role']         = $role;
            $info['values']       = [];

            // create the "mapper" class which will get data from Firefly III.
            $class                = sprintf('App\Services\CSV\Mapper\%s', $info['mapper']);
            if (!class_exists($class)) {
                throw new \InvalidArgumentException(sprintf('Class %s does not exist.', $class));
            }
            Log::debug(sprintf('Associated class is %s', $class));

            /** @var MapperInterface $object */
            $object               = app($class);
            $info['mapping_data'] = $object->getMap();
            $info['mapped']       = $existingMapping[$index] ?? [];

            Log::debug(sprintf('Mapping data length is %d', count($info['mapping_data'])));

            $data[$index]         = $info;
        }

        // get columns from file
        $content         = StorageService::getContent(session()->get(Constants::UPLOAD_DATA_FILE), $configuration->isConversion());
        $delimiter       = (string) config(sprintf('csv.delimiters.%s', $configuration->getDelimiter()));

        $result          = MapperService::getMapData($content, $delimiter, $configuration->isHeaders(), $configuration->getSpecifics(), $data);

        // sort the column on if they're mapped or not.
        foreach ($result as $index => $set) {
            $values                   = $set['values'];
            $mapped                   = array_keys($set['mapped']);
            usort($values, function (string $a, string $b) use ($mapped) {
                if (in_array($a, $mapped, true) && !in_array($b, $mapped, true)) {
                    return 1;
                }

                return -1;
            });
            $result[$index]['values'] = $values;
        }

        return $result;
    }

    /**
     * Return the map data necessary for the importable file mapping based on some weird helpers.
     * TODO needs refactoring and proper splitting into helpers.
     */
    private function getCamtMapInformation(): array
    {
        $configuration   = $this->restoreConfiguration();
        $roles           = $configuration->getRoles();
        $existingMapping = $configuration->getMapping();
        $doMapping       = $configuration->getDoMapping();
        $data            = [];

        foreach ($roles as $index => $role) {
            $info                 = config('camt.all_roles')[$role] ?? null;
            $mappable             = $info['mappable'] ?? false;
            if (null === $info) {
                Log::warning(sprintf('Field "%s" with role "%s" does not exist.', $index, $role));

                continue;
            }
            if (false === $mappable) {
                Log::warning(sprintf('Field "%s" with role "%s" cannot be mapped.', $index, $role));

                continue;
            }
            $mapColumn            = $doMapping[$index] ?? false;
            if (false === $mapColumn) {
                Log::warning(sprintf('Field "%s" with role "%s" does not have to be mapped.', $index, $role));

                continue;
            }
            Log::debug(sprintf('Field "%s" with role is "%s"', $index, $role));

            $info['role']         = $role;
            $info['values']       = [];

            // create the "mapper" class which will get data from Firefly III.
            $class                = sprintf('App\Services\CSV\Mapper\%s', $info['mapper']);
            if (!class_exists($class)) {
                throw new \InvalidArgumentException(sprintf('Class %s does not exist.', $class));
            }
            Log::debug(sprintf('Associated class is %s', $class));

            /** @var MapperInterface $object */
            $object               = app($class);
            $info['mapping_data'] = $object->getMap();
            $info['mapped']       = $existingMapping[$index] ?? [];

            Log::debug(sprintf('Mapping data length is %d', count($info['mapping_data'])));

            $data[$index]         = $info;
        }

        // get columns from file
        $content         = StorageService::getContent(session()->get(Constants::UPLOAD_DATA_FILE), $configuration->isConversion());

        return MapperService::getMapDataForCamt($configuration, $content, $data);
    }

    /**
     * Weird bunch of code to return info on Spectre and Nordigen.
     */
    private function getImporterMapInformation(): array
    {
        $data            = [];
        $configuration   = $this->restoreConfiguration();
        $existingMapping = $configuration->getMapping();
        /*
         * To map Nordigen and SimpleFIN, pretend the file has one "column" (this is based on the CSV importer after all)
         * that contains:
         * - opposing account names (this is preordained).
         */
        if ('nordigen' === $configuration->getFlow() || 'spectre' === $configuration->getFlow()) {
            // TODO should be in a helper or something generic.
            // index 0, opposing account name:
            $index                        = 0;
            $opposingName                 = config('csv.import_roles.opposing-name') ?? null;
            $opposingName['role']         = 'opposing-name';
            $opposingName['values']       = $this->getOpposingAccounts();

            // create the "mapper" class which will get data from Firefly III.
            $class                        = sprintf('App\Services\CSV\Mapper\%s', $opposingName['mapper']);
            if (!class_exists($class)) {
                throw new \InvalidArgumentException(sprintf('Class %s does not exist.', $class));
            }
            Log::debug(sprintf('Associated class is %s', $class));

            /** @var MapperInterface $object */
            $object                       = app($class);
            $opposingName['mapping_data'] = $object->getMap();
            $opposingName['mapped']       = $existingMapping[$index] ?? [];
            $data[]                       = $opposingName;
        }
        if ('spectre' === $configuration->getFlow()) {
            // index 1: category (TODO)
            // index 0, category name:
            $index                    = 1;
            $category                 = config('csv.import_roles.category-name') ?? null;
            $category['role']         = 'category-name';
            $category['values']       = $this->getCategories();

            // create the "mapper" class which will get data from Firefly III.
            $class                    = sprintf('App\Services\CSV\Mapper\%s', $category['mapper']);
            if (!class_exists($class)) {
                throw new \InvalidArgumentException(sprintf('Class %s does not exist.', $class));
            }
            Log::debug(sprintf('Associated class is %s', $class));

            /** @var MapperInterface $object */
            $object                   = app($class);
            $category['mapping_data'] = $object->getMap();
            $category['mapped']       = $existingMapping[$index] ?? [];
            $data[]                   = $category;
        }
        if ('simplefin' === $configuration->getFlow()) {
            // index 0: expense/revenue account mapping
            $index                          = 0;
            $expenseRevenue                 = config('csv.import_roles.opposing-name') ?? null;
            $expenseRevenue['role']         = 'opposing-name';
            $expenseRevenue['values']       = $this->getExpenseRevenueAccounts();

            // Use ExpenseRevenueAccounts mapper for SimpleFIN
            $class                          = 'App\Services\CSV\Mapper\ExpenseRevenueAccounts';
            if (!class_exists($class)) {
                throw new \InvalidArgumentException(sprintf('Class %s does not exist.', $class));
            }
            Log::debug(sprintf('Associated class is %s', $class));

            /** @var MapperInterface $object */
            $object                         = app($class);
            $expenseRevenue['mapping_data'] = $object->getMap();
            $expenseRevenue['mapped']       = $existingMapping[$index] ?? [];
            $data[]                         = $expenseRevenue;
        }

        return $data;
    }

    private function getOpposingAccounts(): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        $downloadIdentifier = session()->get(Constants::CONVERSION_JOB_IDENTIFIER);

        if (null === $downloadIdentifier) {
            Log::warning('No conversion job identifier found in session - mapping called before conversion');

            return [];
        }

        $disk               = Storage::disk(self::DISK_NAME);

        if (!$disk->exists(sprintf('%s.json', $downloadIdentifier))) {
            Log::warning(sprintf('Conversion file %s.json does not exist - mapping called before conversion', $downloadIdentifier));

            return [];
        }

        $json               = $disk->get(sprintf('%s.json', $downloadIdentifier));

        if (null === $json) {
            Log::warning(sprintf('Conversion file %s.json is empty', $downloadIdentifier));

            return [];
        }

        try {
            $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ImporterErrorException(sprintf('Could not decode download: %s', $e->getMessage()), 0, $e);
        }
        $opposing           = [];
        $total              = count($array);

        /** @var array $transaction */
        foreach ($array as $index => $transaction) {
            Log::debug(sprintf('[%s/%s] Parsing transaction (1)', $index + 1, $total));

            /** @var array $row */
            foreach ($transaction['transactions'] as $row) {
                $opposing[] = (string) (array_key_exists('destination_name', $row) ? $row['destination_name'] : '');
                $opposing[] = (string) (array_key_exists('source_name', $row) ? $row['source_name'] : '');
            }
        }
        $filtered           = array_filter(
            $opposing,
            static function (string $value) {
                return '' !== $value;
            }
        );

        return array_unique($filtered);
    }

    private function getExpenseRevenueAccounts(): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        $downloadIdentifier = session()->get(Constants::CONVERSION_JOB_IDENTIFIER);

        if (null === $downloadIdentifier) {
            Log::warning('No conversion job identifier found in session - mapping called before conversion');

            return [];
        }

        $disk               = Storage::disk(self::DISK_NAME);

        if (!$disk->exists(sprintf('%s.json', $downloadIdentifier))) {
            Log::warning(sprintf('Conversion file %s.json does not exist - mapping called before conversion', $downloadIdentifier));

            return [];
        }

        $json               = $disk->get(sprintf('%s.json', $downloadIdentifier));

        if (null === $json) {
            Log::warning(sprintf('Conversion file %s.json is empty', $downloadIdentifier));

            return [];
        }

        try {
            $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ImporterErrorException(sprintf('Could not decode download: %s', $e->getMessage()), 0, $e);
        }
        $expenseRevenue     = [];
        $total              = count($array);

        /** @var array $transaction */
        foreach ($array as $index => $transaction) {
            Log::debug(sprintf('[%s/%s] Parsing transaction for expense/revenue accounts', $index + 1, $total));

            /** @var array $row */
            foreach ($transaction['transactions'] as $row) {
                // Extract expense/revenue destination names from SimpleFIN transactions
                $destinationName = (string) (array_key_exists('destination_name', $row) ? $row['destination_name'] : '');
                $sourceName      = (string) (array_key_exists('source_name', $row) ? $row['source_name'] : '');

                // Add both source and destination names as potential expense/revenue accounts
                if ('' !== $destinationName) {
                    $expenseRevenue[] = $destinationName;
                }
                if ('' !== $sourceName) {
                    $expenseRevenue[] = $sourceName;
                }
            }
        }
        // removed, filtering has already happened in the foreach loop above
        //        $filtered           = array_filter(
        //            $expenseRevenue,
        //            static function (string $value) {
        //                return '' !== $value;
        //            }
        //        );

        return array_unique($expenseRevenue);
    }

    private function getCategories(): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        $downloadIdentifier = session()->get(Constants::CONVERSION_JOB_IDENTIFIER);
        $disk               = Storage::disk(self::DISK_NAME);
        $json               = $disk->get(sprintf('%s.json', $downloadIdentifier));

        try {
            $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ImporterErrorException(sprintf('Could not decode download: %s', $e->getMessage()), 0, $e);
        }
        $categories         = [];
        $total              = count($array);

        /** @var array $transaction */
        foreach ($array as $index => $transaction) {
            Log::debug(sprintf('[%s/%s] Parsing transaction (2)', $index + 1, $total));

            /** @var array $row */
            foreach ($transaction['transactions'] as $row) {
                $categories[] = (string) (array_key_exists('category_name', $row) ? $row['category_name'] : '');
            }
        }
        $filtered           = array_filter(
            $categories,
            static function (string $value) {
                return '' !== $value;
            }
        );

        return array_unique($filtered);
    }

    public function postIndex(Request $request): RedirectResponse
    {
        $values          = $request->get('values') ?? [];
        $mapping         = $request->get('mapping') ?? [];
        $values          = !is_array($values) ? [] : $values;
        $mapping         = !is_array($mapping) ? [] : $mapping;
        $data            = [];

        $configuration   = $this->restoreConfiguration();

        /*
         * Loop array with available columns.
         *
         * @var int   $index
         * @var array $row
         */
        foreach ($values as $columnIndex => $column) {
            /**
             * Loop all values for this column
             *
             * @var int    $valueIndex
             * @var string $value
             */
            foreach ($column as $valueIndex => $value) {
                $mappedValue = $mapping[$columnIndex][$valueIndex] ?? null;
                if (null !== $mappedValue && 0 !== $mappedValue && '0' !== $mappedValue) {
                    $data[$columnIndex][$value] = (int) $mappedValue;
                }
            }
        }

        // at this point the $data array must be merged with the mapping as it is on the disk,
        // and then saved to disk once again in a new config file.
        $configFileName  = session()->get(Constants::UPLOAD_CONFIG_FILE);
        $originalMapping = [];
        $diskConfig      = null;
        if (null !== $configFileName) {
            $diskArray       = json_decode(StorageService::getContent($configFileName), true, JSON_THROW_ON_ERROR);
            $diskConfig      = Configuration::fromArray($diskArray);
            $originalMapping = $diskConfig->getMapping();
        }

        // loop $data and save values:
        $mergedMapping   = $this->mergeMapping($originalMapping, $data);

        $configuration->setMapping($mergedMapping);

        // store mapping in config object ( + session)
        session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());

        // since the configuration saved in the session will omit 'mapping', 'do_mapping' and 'roles'
        // these must be set to the configuration file
        // no need to do this sooner because toSessionArray would have dropped them anyway.
        if (null !== $diskConfig) {
            $configuration->setRoles($diskConfig->getRoles());
            $configuration->setDoMapping($diskConfig->getDoMapping());
        }

        // then save entire thing to a new disk file:
        // TODO write config needs helper too
        $configFileName  = StorageService::storeArray($configuration->toArray());
        Log::debug(sprintf('Old configuration was stored under key "%s".', session()->get(Constants::UPLOAD_CONFIG_FILE)));

        session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);

        Log::debug(sprintf('New configuration is stored under key "%s".', session()->get(Constants::UPLOAD_CONFIG_FILE)));

        // set map config as complete.
        session()->put(Constants::MAPPING_COMPLETE_INDICATOR, true);
        session()->put(Constants::READY_FOR_CONVERSION, true);
        if ('nordigen' === $configuration->getFlow() || 'spectre' === $configuration->getFlow() || 'simplefin' === $configuration->getFlow()) {
            // if nordigen, spectre, or simplefin, now ready for submission!
            session()->put(Constants::READY_FOR_SUBMISSION, true);

            return redirect()->route('008-submit.index');
        }

        return redirect()->route('007-convert.index');
    }

    private function mergeMapping(array $original, array $new): array
    {
        Log::debug('Now merging disk mapping with new mapping');
        foreach ($new as $column => $mappedValues) {
            Log::debug(sprintf('Now working on column "%s"', $column));
            if (array_key_exists($column, $original)) {
                foreach ($mappedValues as $name => $value) {
                    Log::debug(sprintf('Updated mapping of "%s" to ID "%s"', $name, $value));
                    $original[$column][$name] = $value;
                }
            }
            if (!array_key_exists($column, $original)) {
                Log::debug('The original mapping has no map data for this column. We will set it now.');
                $original[$column] = $mappedValues;
            }
        }

        // original has been updated:
        return $original;
    }
}
