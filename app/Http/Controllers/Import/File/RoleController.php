<?php
/*
 * RoleController.php
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

declare(strict_types=1);

namespace App\Http\Controllers\Import\File;


use App\Http\Controllers\Controller;
use App\Http\Middleware\RoleControllerMiddleware;
use App\Http\Request\RolesPostRequest;
use App\Services\CSV\Roles\RoleService;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Storage\StorageService;
use App\Support\Http\RestoresConfiguration;
use App\Support\Http\ValidatesCombinations;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JsonException;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\UnableToProcessCsv;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function app;
use function config;
use function redirect;
use function session;
use function view;

/**
 * Class RoleController
 */
class RoleController extends Controller
{
    use RestoresConfiguration, ValidatesCombinations;

    /**
     * RoleController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Define roles');
        $this->middleware(RoleControllerMiddleware::class);
    }

    /**
     * @param Request $request
     * @return Factory|View
     * @throws JsonException
     * @throws Exception
     * @throws InvalidArgument
     * @throws UnableToProcessCsv
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function index(Request $request)
    {
        app('log')->debug('Now in role controller');
        $mainTitle           = 'Role definition';
        $subTitle            = 'Configure the role of each column in your file(s)';
        $singleConfiguration = session()->get(Constants::SINGLE_CONFIGURATION_SESSION);

        // could be multi import
        // TODO this code must be in a helper:
        $combinations = session()->get(Constants::UPLOADED_COMBINATIONS);
        $this->validatesCombinations();
        $data = [];
        app('log')->debug(sprintf('Array has %d configuration(s)', count($combinations)));
        /** @var array $entry */
        foreach ($combinations as $index => $entry) {
            app('log')->debug(sprintf('[%d/%d] processing configuration.', ($index + 1), count($combinations)));
            $data[] = $this->processCombination($entry);
        }

        // roles
        $roles = config('csv.import_roles');
        ksort($roles);

        return view('import.005-roles.index', compact('mainTitle', 'subTitle', 'roles', 'data', 'singleConfiguration'));
    }

    /**
     * TODO move to helper
     * @param array $entry
     * @return array
     * @throws Exception
     * @throws InvalidArgument
     * @throws JsonException
     * @throws UnableToProcessCsv
     */
    private function processCombination(array $entry): array
    {
        $return                  = $entry;
        $configuration           = $this->restoreConfigurationFromFile($entry['config_location']);
        $flow                    = $configuration->getFlow();
        $return['configuration'] = $configuration;
        $return['flow']          = $flow;
        $return['type']          = $entry['type'];

        switch ($return['type']) {
            case 'csv':
                // get columns and examples from the uploaded file:
                $content            = StorageService::getContent($entry['storage_location'], $configuration->isConversion());
                $return['columns']  = RoleService::getColumns($content, $configuration);
                $return['examples'] = RoleService::getExampleData($content, $configuration);

                // get things from configuration ready:
                $return['mapping']    = base64_encode(json_encode($configuration->getMapping(), JSON_THROW_ON_ERROR));
                $return['roles']      = $configuration->getRoles();
                $return['do_mapping'] = $configuration->getDoMapping();
                break;
            case 'xml':
                // this type may need further processing.
                $return['columns']    = [];
                $return['examples']   = [];
                $return['mapping']    = [];
                $return['roles']      = [];
                $return['do_mapping'] = [];
                break;
        }
        return $return;
    }

    /**
     * @param RolesPostRequest $request
     *
     * @return RedirectResponse
     * @throws JsonException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function postIndex(RolesPostRequest $request): RedirectResponse
    {
        $data = $request->getAll();

        // TODO this code must be in a helper:
        $combinations    = session()->get(Constants::UPLOADED_COMBINATIONS);
        $newCombinations = [];
        if (!is_array($combinations)) {
            die('Must be array');
        }
        if (count($combinations) < 1) {
            die('Must be more than zero.');
        }
        $needsMapping = false;
        /**
         * @var int   $index
         * @var array $entry
         */
        foreach ($combinations as $index => $entry) {
            $dataSet = $data['configurations'][$index];
            // restore configuration (this time from array!)
            $object       = Configuration::fromArray(json_decode(StorageService::getContent($entry['config_location']), true));
            $needsMapping = true === $needsMapping || $this->needMapping($dataSet['do_mapping']);
            $object->setRoles($dataSet['roles']);
            $object->setDoMapping($dataSet['do_mapping']);

            // then this is the new, full array:
            $fullArray = $object->toArray();

            // and it can be saved on disk:
            $configFileName = StorageService::storeArray($fullArray);
            app('log')->debug(sprintf('Old configuration was stored under key "%s".', $entry['config_location']));
            app('log')->debug(sprintf('New configuration is stored under key "%s".', $configFileName));
            // new
            $entry['config_location'] = $configFileName;
            $newCombinations[]        = $entry;

        }
        // set role config as complete.
        session()->put(Constants::ROLES_COMPLETE_INDICATOR, true);
        session()->put(Constants::UPLOADED_COMBINATIONS, $newCombinations);

        if (true === $needsMapping) {
            return redirect()->route('006-mapping.index');
        }

        session()->put(Constants::READY_FOR_CONVERSION, true);
        return redirect()->route('007-convert.index');
    }

    /**
     * TODO move to helper
     *
     * Will tell you if any role needs mapping.
     *
     * @param array $array
     *
     * @return bool
     */
    private function needMapping(array $array): bool
    {
        $need = false;
        foreach ($array as $value) {
            if (true === $value) {
                $need = true;
            }
        }

        return $need;
    }
}
