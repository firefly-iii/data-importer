<?php
declare(strict_types=1);
/**
 * MapController.php
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


use App\Http\Controllers\Controller;
use App\Http\Middleware\MappingComplete;
use App\Services\CSV\Configuration\Configuration;
use App\Services\CSV\Mapper\MapperInterface;
use App\Services\CSV\Mapper\MapperService;
use App\Services\Session\Constants;
use App\Services\Storage\StorageService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use League\Csv\Exception;
use Log;

/**
 * Class MapController
 */
class MapController extends Controller
{

    /**
     * RoleController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Map data');
        $this->middleware(MappingComplete::class);
    }

    /**
     * @return Factory|View
     * @throws Exception
     * @throws FileNotFoundException
     */
    public function index()
    {
        $mainTitle = 'Map data';
        $subTitle  = 'Map values in CSV file to actual data in Firefly III';
        Log::debug('Now in mapController index');

        // get configuration object.
        $configuration = Configuration::fromArray(session()->get(Constants::CONFIGURATION));

        // the config in the session will miss important values, we must get those from disk:
        // 'mapping', 'do_mapping', 'roles' are missing.
        $diskArray  = json_decode(StorageService::getContent(session()->get(Constants::UPLOAD_CONFIG_FILE)), true, JSON_THROW_ON_ERROR);
        $diskConfig = Configuration::fromArray($diskArray);

        $configuration->setMapping($diskConfig->getMapping());
        $configuration->setDoMapping($diskConfig->getDoMapping());
        $configuration->setRoles($diskConfig->getRoles());

        // then we can use them:
        $roles           = $configuration->getRoles();
        $existingMapping = $configuration->getMapping();
        $doMapping       = $configuration->getDoMapping();
        $data            = [];

        foreach ($roles as $index => $role) {
            $info     = config('csv_importer.import_roles')[$role] ?? null;
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
            Log::debug(sprintf('Mappable role is "%s"', $role));

            $info['role']   = $role;
            $info['values'] = [];


            // create the "mapper" class which will get data from Firefly III.
            $class = sprintf('App\\Services\\CSV\\Mapper\\%s', $info['mapper']);
            if (!class_exists($class)) {
                throw new InvalidArgumentException(sprintf('Class %s does not exist.', $class));
            }
            Log::debug(sprintf('Associated class is %s', $class));


            /** @var MapperInterface $object */
            $object               = app($class);
            $info['mapping_data'] = $object->getMap();
            $info['mapped']       = $existingMapping[$index] ?? [];

            Log::debug(sprintf('Mapping data length is %d', count($info['mapping_data'])));

            $data[$index] = $info;
        }

        // get columns from file
        $content   = StorageService::getContent(session()->get(Constants::UPLOAD_CSV_FILE));
        $delimiter = (string) config(sprintf('csv_importer.delimiters.%s', $configuration->getDelimiter()));
        $data      = MapperService::getMapData($content, $delimiter, $configuration->isHeaders(), $configuration->getSpecifics(), $data);

        return view('import.map.index', compact('mainTitle', 'subTitle', 'roles', 'data'));
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function postIndex(Request $request): RedirectResponse
    {
        $values  = $request->get('values') ?? [];
        $mapping = $request->get('mapping') ?? [];
        $values  = !is_array($values) ? [] : $values;
        $mapping = !is_array($mapping) ? [] : $mapping;
        $data    = [];

        $configuration = Configuration::fromArray(session()->get(Constants::CONFIGURATION));

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
                    $data[$columnIndex][$value] = (int) $mappedValue;
                }

            }
        }

        // at this point the $data array must be merged with the mapping as it is on the disk,
        // and then saved to disk once again in a new config file.
        $diskArray       = json_decode(StorageService::getContent(session()->get(Constants::UPLOAD_CONFIG_FILE)), true, JSON_THROW_ON_ERROR);
        $diskConfig      = Configuration::fromArray($diskArray);
        $originalMapping = $diskConfig->getMapping();

        // loop $data and save values:
        $mergedMapping = $this->mergeMapping($originalMapping, $data);

        $configuration->setMapping($mergedMapping);

        // store mapping in config object ( + session)
        session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());

        // since the configuration saved in the session will omit 'mapping', 'do_mapping' and 'roles'
        // these must be set to the configuration file
        // no need to do this sooner because toSessionArray would have dropped them anyway.
        $configuration->setRoles($diskConfig->getRoles());
        $configuration->setDoMapping($diskConfig->getDoMapping());

        // then save entire thing to a new disk file:
        $configFileName = StorageService::storeArray($configuration->toArray());
        Log::debug(sprintf('Old configuration was stored under key "%s".', session()->get(Constants::UPLOAD_CONFIG_FILE)));

        session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);

        Log::debug(sprintf('New configuration is stored under key "%s".', session()->get(Constants::UPLOAD_CONFIG_FILE)));

        // set map config as complete.
        session()->put(Constants::MAPPING_COMPLETE_INDICATOR, true);

        return redirect()->route('import.run.index');
    }

    /**
     * @param array $original
     * @param array $new
     * @return array
     */
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
