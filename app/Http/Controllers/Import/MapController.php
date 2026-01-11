<?php

/*
 * MapController.php
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

use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\CSV\Mapper\MapperInterface;
use App\Services\CSV\Mapper\MapperService;
use App\Services\CSV\Mapper\OpposingAccounts;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Class MapController
 */
class MapController extends Controller
{
    protected const string DISK_NAME = 'jobs';
    private ImportJobRepository $repository;

    /**
     * RoleController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        app('view')->share('pageTitle', 'Map data');
        $this->repository = app(ImportJobRepository::class);
    }

    /**
     * @return Factory|RedirectResponse|View
     */
    public function index(string $identifier)
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $mainTitle     = 'Map data';
        $subTitle      = 'Map values in file to actual data in Firefly III';
        $importJob     = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();
        $data          = [];
        $roles         = [];

        $state         = $importJob->getState();
        if ('new' === $state || 'contains_content' === $state || 'is_parsed' === $state || 'is_configured' === $state) {
            exit(sprintf('Job is in state "%s" so not ready for this step. Needs a better page.', $state));
        }

        // file things
        if ('file' === $importJob->getFlow() && 'csv' === $configuration->getContentType()) {
            Log::debug('Get mapping data for CSV file');
            $roles = $configuration->getRoles();
            $data  = $this->getCSVMapInformation($importJob);
        }
        if ('file' === $importJob->getFlow() && 'camt' === $configuration->getContentType()) {
            Log::debug('Get mapping data for CAMT file');
            $roles = $configuration->getRoles();
            $data  = $this->getCamtMapInformation($importJob);
        }

        // nordigen, spectre, simplefin and others:
        if ('file' !== $importJob->getFlow()) {
            Log::debug('Get mapping data for data importers.');
            $roles = [];
            $data  = $this->getImporterMapInformation($importJob);
        }
        // if nothing to map, just set mappable to true and go to the next step:
        if (0 === count($data)) {
            $flow                    = $importJob->getFlow();
            $conversionBeforeMapping = config(sprintf('importer.providers.%s.conversion_before_mapping', $flow));
            if ($conversionBeforeMapping) {
                // is already converted, so ready for submission.
                $importJob->setState('ready_for_submission');
                $redirect = route('submit-data.index', [$identifier]);
            }
            if (!$conversionBeforeMapping) {
                $importJob->setState('configured_roles_map_in_place');
                $redirect = route('data-conversion.index', [$identifier]);
            }
            $this->repository->saveToDisk($importJob);

            return view('import.006-mapping.no-mapping', compact('mainTitle', 'subTitle', 'identifier', 'roles', 'data', 'redirect'));
        }

        return view('import.006-mapping.index', compact('mainTitle', 'subTitle', 'identifier', 'roles', 'data'));
    }

    /**
     * Return the map data necessary for the importable file mapping based on some weird helpers.
     * FIXME needs refactoring and proper splitting into helpers.
     * FIXME needs renaming or specific CAMT counterpart.
     */
    private function getCSVMapInformation(ImportJob $importJob): array
    {
        $configuration   = $importJob->getConfiguration();
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
                throw new InvalidArgumentException(sprintf('Class %s does not exist.', $class));
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
        $content         = $importJob->getImportableFileString($configuration->isConversion());
        $delimiter       = (string)config(sprintf('csv.delimiters.%s', $configuration->getDelimiter()));
        $result          = MapperService::getMapData($content, $delimiter, $configuration->isHeaders(), $data);

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
     * FIXME needs refactoring and proper splitting into helpers.
     */
    private function getCamtMapInformation(ImportJob $importJob): array
    {
        $configuration   = $importJob->getConfiguration();
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
                throw new InvalidArgumentException(sprintf('Class %s does not exist.', $class));
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
        return MapperService::getMapDataForCamt($configuration, $importJob->getImportableFileString($configuration->isConversion()), $data);
    }

    /**
     * Weird bunch of code to return info on Spectre and Nordigen.
     */
    private function getImporterMapInformation(ImportJob $importJob): array
    {
        $data            = [];
        $configuration   = $importJob->getConfiguration();
        $existingMapping = $configuration->getMapping();
        /*
         * To map Nordigen and SimpleFIN, pretend the file has one "column" (this is based on the CSV importer after all)
         * that contains:
         * - opposing account names (this is preordained).
         */
        if (
            'nordigen' === $importJob->getFlow() || 'sophtron' === $importJob->getFlow()
            || 'spectre' === $importJob->getFlow() || 'lunchflow' === $importJob->getFlow()) {
            // FIXME should be in a helper or something generic.
            // index 0, opposing account name:
            $index                        = 0;
            $opposingName                 = config('csv.import_roles.opposing-name') ?? null;
            $opposingName['role']         = 'opposing-name';
            $opposingName['values']       = $this->getOpposingAccounts($importJob);

            // create the "mapper" class which will get data from Firefly III.
            $class                        = sprintf('App\Services\CSV\Mapper\%s', $opposingName['mapper']);
            if (!class_exists($class)) {
                throw new InvalidArgumentException(sprintf('Class %s does not exist.', $class));
            }
            Log::debug(sprintf('Associated class is %s', $class));

            /** @var MapperInterface $object */
            $object                       = app($class);
            $opposingName['mapping_data'] = $object->getMap();
            $opposingName['mapped']       = $existingMapping[$index] ?? [];
            $data[]                       = $opposingName;

            return $data;
        }
        if ('simplefin' === $importJob->getFlow()) {

            // index 0: expense/revenue account mapping
            $index                          = 0;
            $expenseRevenue                 = config('csv.import_roles.opposing-name') ?? null;
            $expenseRevenue['role']         = 'opposing-name';
            $expenseRevenue['values']       = $this->getExpenseRevenueAccounts($importJob);

            // Use ExpenseRevenueAccounts mapper for SimpleFIN
            $class                          = OpposingAccounts::class;
            if (!class_exists($class)) {
                throw new InvalidArgumentException(sprintf('Class %s does not exist.', $class));
            }
            Log::debug(sprintf('Associated class is %s', $class));

            /** @var MapperInterface $object */
            $object                         = app($class);
            $expenseRevenue['mapping_data'] = $object->getMap();
            $expenseRevenue['mapped']       = $existingMapping[$index] ?? [];
            $data[]                         = $expenseRevenue;

            return $data;
        }

        throw new ImporterErrorException(sprintf('Cannot map data for import flow "%s"', $importJob->getFlow()));
    }

    private function getOpposingAccounts(ImportJob $importJob): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $opposing = [];
        $array    = $importJob->getConvertedTransactions();
        $total    = count($importJob->getConvertedTransactions());

        /** @var array $transaction */
        foreach ($array as $index => $transaction) {
            Log::debug(sprintf('[%s/%s] Parsing transaction (1)', $index + 1, $total));

            /** @var array $row */
            foreach ($transaction['transactions'] as $row) {
                $opposing[] = (string)(array_key_exists('destination_name', $row) ? $row['destination_name'] : '');
                $opposing[] = (string)(array_key_exists('source_name', $row) ? $row['source_name'] : '');
            }
        }
        $filtered = array_filter(
            $opposing,
            static fn (string $value) => '' !== $value
        );

        return array_unique($filtered);
    }

    private function getExpenseRevenueAccounts(ImportJob $importJob): array
    {
        $transactions   = $importJob->getConvertedTransactions();
        $expenseRevenue = [];
        $total          = count($transactions);

        /** @var array $transaction */
        foreach ($transactions as $index => $transaction) {
            Log::debug(sprintf('[%s/%s] Parsing transaction for expense/revenue accounts', $index + 1, $total));

            /** @var array $row */
            foreach ($transaction['transactions'] as $row) {
                // Extract expense/revenue destination names from SimpleFIN transactions
                $destinationName = (string)(array_key_exists('destination_name', $row) ? $row['destination_name'] : '');
                $sourceName      = (string)(array_key_exists('source_name', $row) ? $row['source_name'] : '');

                // Add both source and destination names as potential expense/revenue accounts
                if ('' !== $destinationName) {
                    $expenseRevenue[] = $destinationName;
                }
                if ('' !== $sourceName) {
                    $expenseRevenue[] = $sourceName;
                }
            }
        }

        return array_unique($expenseRevenue);
    }

    public function postIndex(Request $request, string $identifier): RedirectResponse
    {
        $values                  = $request->get('values') ?? [];
        $mapping                 = $request->get('mapping') ?? [];
        $values                  = !is_array($values) ? [] : $values;
        $mapping                 = !is_array($mapping) ? [] : $mapping;
        $data                    = [];
        $importJob               = $this->repository->find($identifier);
        $configuration           = $importJob->getConfiguration();

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
                    $data[$columnIndex][$value] = (int)$mappedValue;
                }
            }
        }

        // at this point the $data array must be merged with the mapping as it is on the disk,
        // and then saved to disk once again in a new config file.
        $originalMapping         = $configuration->getMapping();

        // loop $data and save values:
        $mergedMapping           = $this->mergeMapping($originalMapping, $data);
        $configuration->setMapping($mergedMapping);
        $importJob->setConfiguration($configuration);

        $flow                    = $importJob->getFlow();
        $conversionBeforeMapping = config(sprintf('importer.providers.%s.conversion_before_mapping', $flow));
        Log::debug(sprintf('Conversion before mapping is %s', var_export($conversionBeforeMapping, true)));
        if ($conversionBeforeMapping) {
            // is already converted, so ready for submission.
            $importJob->setState('ready_for_submission');
            $this->repository->saveToDisk($importJob);
            Log::debug('Redirect to submit data with state "ready_for_submission"');

            return redirect()->route('submit-data.index', [$identifier]);
        }

        Log::debug('Redirect to conversion with state "configured_roles_map_in_place"');
        $importJob->setState('configured_roles_map_in_place');
        $this->repository->saveToDisk($importJob);

        return redirect()->route('data-conversion.index', [$identifier]);
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
