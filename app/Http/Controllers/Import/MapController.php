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
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class MapController
 */
class MapController extends Controller
{
    use RestoresConfiguration;

    protected const DISK_NAME = 'jobs';

    /**
     * MapController     constructor.
     */
    public function __construct()
    {
        parent::__construct();

        app('view')->share('pageTitle', 'Map data');
        $this->middleware(MapControllerMiddleware::class);
    }

    /**
     * @return Factory|View
     */
    public function index()
    {
        app('log')->debug('Now in mapController index');

        $mainTitle           = 'Map data';
        $subTitle            = 'Map values in your file(s) to actual data in Firefly III';
        $singleConfiguration = session()->get(Constants::SINGLE_CONFIGURATION_SESSION);

        // TODO this code must be in a helper:
        $combinations = session()->get(Constants::UPLOADED_COMBINATIONS);
        $data         = [];
        if (!is_array($combinations)) {
            die('Must be array');
        }
        if (count($combinations) < 1) {
            die('Must be more than zero.');
        }
        $needRoles = false;

        /**
         * @var int   $index
         * @var array $entry
         */
        foreach ($combinations as $index => $entry) {
            $set = $entry;
            // restore configuration (this time from array!)
            $object = Configuration::fromArray(json_decode(StorageService::getContent($entry['config_location']), true));

            $set['info']  = [];
            $set['roles'] = [];

            if ('file' === $object->getFlow()) {
                app('log')->debug('Get mapping data for importable file');
                $set['roles'] = $object->getRoles();
                $set['info']  = $this->getCSVMapInformation($entry['config_location'], $entry['storage_location']);
            }

            // nordigen, spectre and others:
            if ('file' !== $object->getFlow()) {
                app('log')->debug('Get mapping data for nordigen and spectre');
                $set['roles'] = [];
                $set['info']  = $this->getImporterMapInformation($entry['storage_location']);
            }
            if (0 === count($set['info'])) {
                $needRoles = true === $needRoles;
                if ('file' === $object->getFlow()) {
                    app('log')->debug('Its a file, also set ready for conversion.');
                    session()->put(Constants::READY_FOR_CONVERSION, true);
                }
            }
            if (0 !== count($set['info'])) {
                $needRoles = true;
            }
            $data[] = $set;
        }

        // if nothing to map, just set mappable to true and go to the next step:
        if (false === $needRoles) {
            session()->put(Constants::MAPPING_COMPLETE_INDICATOR, true);
            return redirect()->route('007-convert.index');
        }

        return view('import.006-mapping.index', compact('mainTitle', 'subTitle', 'data', 'singleConfiguration'));
    }

    /**
     * Return the map data necessary for the importable file mapping based on some weird helpers.
     * TODO needs refactoring and proper splitting into helpers.
     * TODO needs renaming or specific CAMT counterpart.
     *
     * @param string $configKey
     * @param string $fileKey
     * @return array
     * @throws ContainerExceptionInterface
     * @throws ImporterErrorException
     * @throws NotFoundExceptionInterface
     */
    private function getCSVMapInformation(string $configKey, string $fileKey): array
    {
        $configuration   = Configuration::fromArray(json_decode(StorageService::getContent($configKey), true));
        $roles           = $configuration->getRoles();
        $existingMapping = $configuration->getMapping();
        $doMapping       = $configuration->getDoMapping();
        $data            = [];

        foreach ($roles as $index => $role) {
            $info     = config('csv.import_roles')[$role] ?? null;
            $mappable = $info['mappable'] ?? false;
            if (null === $info) {
                continue;
            }
            if (false === $mappable) {
                continue;
            }
            $mapColumn = $doMapping[$index] ?? false;
            if (false === $mapColumn) {
                continue;
            }
            app('log')->debug(sprintf('Mappable role is "%s"', $role));

            $info['role']   = $role;
            $info['values'] = [];


            // create the "mapper" class which will get data from Firefly III.
            $class = sprintf('App\\Services\\CSV\\Mapper\\%s', $info['mapper']);
            if (!class_exists($class)) {
                throw new InvalidArgumentException(sprintf('Class %s does not exist.', $class));
            }
            app('log')->debug(sprintf('Associated class is %s', $class));


            /** @var MapperInterface $object */
            $object               = app($class);
            $info['mapping_data'] = $object->getMap();
            $info['mapped']       = $existingMapping[$index] ?? [];

            app('log')->debug(sprintf('Mapping data length is %d', count($info['mapping_data'])));

            $data[$index] = $info;
        }

        // get columns from file
        $content   = StorageService::getContent($fileKey, $configuration->isConversion());
        $delimiter = (string) config(sprintf('csv.delimiters.%s', $configuration->getDelimiter()));
        return MapperService::getMapData($content, $delimiter, $configuration->isHeaders(), $configuration->getSpecifics(), $data);
    }

    /**
     * TODO move to helper.
     * Weird bunch of code to return info on Spectre and Nordigen.
     * @return array
     * @throws ContainerExceptionInterface
     * @throws FileNotFoundException
     * @throws ImporterErrorException
     * @throws NotFoundExceptionInterface
     */
    private function getImporterMapInformation(): array
    {
        die('getImporterMapInformation does not work yet.');
        $data            = [];
        $configuration   = $this->restoreConfiguration();
        $existingMapping = $configuration->getMapping();
        /*
         * To map Nordigen, pretend the file has one "column" (this is based on the CSV importer after all)
         * that contains:
         * - opposing account names (this is preordained).
         */
        if ('nordigen' === $configuration->getFlow() || 'spectre' === $configuration->getFlow()) {

            // TODO should be in a helper or something generic.
            // index 0, opposing account name:
            $index                  = 0;
            $opposingName           = config('csv.import_roles.opposing-name') ?? null;
            $opposingName['role']   = 'opposing-name';
            $opposingName['values'] = $this->getOpposingAccounts();

            // create the "mapper" class which will get data from Firefly III.
            $class = sprintf('App\\Services\\CSV\\Mapper\\%s', $opposingName['mapper']);
            if (!class_exists($class)) {
                throw new InvalidArgumentException(sprintf('Class %s does not exist.', $class));
            }
            app('log')->debug(sprintf('Associated class is %s', $class));

            /** @var MapperInterface $object */
            $object                       = app($class);
            $opposingName['mapping_data'] = $object->getMap();
            $opposingName['mapped']       = $existingMapping[$index] ?? [];
            $data[]                       = $opposingName;
        }
        if ('spectre' === $configuration->getFlow()) {

            // index 1: category (TODO)
            // index 0, category name:
            $index              = 1;
            $category           = config('csv.import_roles.category-name') ?? null;
            $category['role']   = 'category-name';
            $category['values'] = $this->getCategories();

            // create the "mapper" class which will get data from Firefly III.
            $class = sprintf('App\\Services\\CSV\\Mapper\\%s', $category['mapper']);
            if (!class_exists($class)) {
                throw new InvalidArgumentException(sprintf('Class %s does not exist.', $class));
            }
            app('log')->debug(sprintf('Associated class is %s', $class));

            /** @var MapperInterface $object */
            $object                   = app($class);
            $category['mapping_data'] = $object->getMap();
            $category['mapped']       = $existingMapping[$index] ?? [];
            $data[]                   = $category;
        }
        return $data;
    }

    /**
     * @return array
     * @throws FileNotFoundException
     * @throws ImporterErrorException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     *
     * TODO move to helper or something
     */
    private function getOpposingAccounts(): array
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $downloadIdentifier = session()->get(Constants::CONVERSION_JOB_IDENTIFIER);
        $disk               = Storage::disk(self::DISK_NAME);
        $json               = $disk->get(sprintf('%s.json', $downloadIdentifier));
        try {
            $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ImporterErrorException(sprintf('Could not decode download: %s', $e->getMessage()), 0, $e);
        }
        $opposing = [];
        $total    = count($array);
        /** @var array $transaction */
        foreach ($array as $index => $transaction) {
            app('log')->debug(sprintf('[%s/%s] Parsing transaction (1)', ($index + 1), $total));
            /** @var array $row */
            foreach ($transaction['transactions'] as $row) {
                $opposing[] = (string) array_key_exists('destination_name', $row) ? $row['destination_name'] : '';
                $opposing[] = (string) array_key_exists('source_name', $row) ? $row['source_name'] : '';
            }

        }
        $filtered = array_filter(
            $opposing,
            static function (string $value) {
                return '' !== $value;
            }
        );

        return array_unique($filtered);
    }

    /**
     * TODO move to helper.
     * @return array
     * @throws ContainerExceptionInterface
     * @throws FileNotFoundException
     * @throws ImporterErrorException
     * @throws NotFoundExceptionInterface
     */
    private function getCategories(): array
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $downloadIdentifier = session()->get(Constants::CONVERSION_JOB_IDENTIFIER);
        $disk               = Storage::disk(self::DISK_NAME);
        $json               = $disk->get(sprintf('%s.json', $downloadIdentifier));
        try {
            $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ImporterErrorException(sprintf('Could not decode download: %s', $e->getMessage()), 0, $e);
        }
        $categories = [];
        $total      = count($array);
        /** @var array $transaction */
        foreach ($array as $index => $transaction) {
            app('log')->debug(sprintf('[%s/%s] Parsing transaction (2)', ($index + 1), $total));
            /** @var array $row */
            foreach ($transaction['transactions'] as $row) {
                $categories[] = (string) array_key_exists('category_name', $row) ? $row['category_name'] : '';
            }

        }
        $filtered = array_filter(
            $categories,
            static function (?string $value) {
                return '' !== (string) $value;
            }
        );

        return array_unique($filtered);
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse
     * @throws ContainerExceptionInterface
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     */
    public function postIndex(Request $request): RedirectResponse
    {
        $data = $request->all();

        // TODO this code must be in a helper:
        $combinations    = session()->get(Constants::UPLOADED_COMBINATIONS);
        $newCombinations = [];
        if (!is_array($combinations)) {
            die('Must be array');
        }
        if (count($combinations) < 1) {
            die('Must be more than zero.');
        }
        foreach ($combinations as $index => $entry) {
            // restore configuration (this time from array!)
            $object = Configuration::fromArray(json_decode(StorageService::getContent($entry['config_location']), true));

            $values  = $data['values'][$index] ?? [];
            $mapping = $data['mapping'][$index] ?? [];
            $values  = !is_array($values) ? [] : $values;
            $mapping = !is_array($mapping) ? [] : $mapping;
            // was $data
            $current = [];

            /**
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
                        $current[$columnIndex][$value] = (int) $mappedValue;
                    }

                }
            }

            // at this point the $current array must be merged with the mapping as it is on the disk,
            // and then saved to disk once again in a new config file.
            $originalMapping = $object->getMapping();
            // loop $current and save values:
            $mergedMapping = $this->mergeMapping($originalMapping, $current);
            $object->setMapping($mergedMapping);

            // then save entire thing to a new disk file:
            app('log')->debug(sprintf('Old configuration was stored under key "%s".', $entry['config_location']));
            $entry['config_location'] = StorageService::storeArray($object->toArray());
            app('log')->debug(sprintf('New configuration is stored under key "%s".', $entry['config_location']));
            // set map config as complete.
            session()->put(Constants::MAPPING_COMPLETE_INDICATOR, true);
            session()->put(Constants::READY_FOR_CONVERSION, true);
            if ('nordigen' === $object->getFlow() || 'spectre' === $object->getFlow()) {
                // if nordigen, now ready for submission!
                session()->put(Constants::READY_FOR_SUBMISSION, true);
            }
        }

        return redirect()->route('007-convert.index');
    }

    /**
     * TODO move to helper.
     * @param array $original
     * @param array $new
     * @return array
     */
    private function mergeMapping(array $original, array $new): array
    {
        app('log')->debug('Now merging disk mapping with new mapping');
        foreach ($new as $column => $mappedValues) {
            app('log')->debug(sprintf('Now working on column "%s"', $column));
            if (array_key_exists($column, $original)) {
                foreach ($mappedValues as $name => $value) {
                    app('log')->debug(sprintf('Updated mapping of "%s" to ID "%s"', $name, $value));
                    $original[$column][$name] = $value;
                }
            }
            if (!array_key_exists($column, $original)) {
                app('log')->debug('The original mapping has no map data for this column. We will set it now.');
                $original[$column] = $mappedValues;
            }
        }
        // original has been updated:
        return $original;
    }
}
