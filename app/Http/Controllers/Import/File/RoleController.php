<?php
/*
 * RoleController.php
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

namespace App\Http\Controllers\Import\File;

use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\RoleControllerMiddleware;
use App\Http\Request\RolesPostRequest;
use App\Services\CSV\Roles\RoleService;
use App\Services\Camt053\Converter;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Storage\StorageService;
use App\Support\Http\RestoresConfiguration;
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

/**
 * Class RoleController
 */
class RoleController extends Controller
{
    use RestoresConfiguration;

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
     *
     * @return Factory|View
     * @throws JsonException
     * @throws Exception
     * @throws InvalidArgument
     * @throws UnableToProcessCsv
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ImporterErrorException
     */
    public function index(Request $request)
    {
        app('log')->debug('Now in Role controller');
        $flow = $request->cookie(Constants::FLOW_COOKIE);
        if ('file' !== $flow) {
            die('redirect or something');
        }
        // get configuration object.
        $configuration = $this->restoreConfiguration();
        $contentType   = $configuration->getContentType();

        switch ($contentType) {
            default:
                throw new ImporterErrorException(sprintf('Cannot handle file type "%s"', $contentType));
            case 'csv':
                return $this->csvIndex($request, $configuration);
            case 'camt':
                return $this->camtIndex($request, $configuration);

        }
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
        // the request object must be able to handle all file types.
        $configuration = $this->restoreConfiguration();
        $contentType   = $configuration->getContentType();

        switch ($contentType) {
            default:
                throw new ImporterErrorException(sprintf('Cannot handle file type "%s" in POST.', $contentType));
            case 'csv':
                return $this->csvPostIndex($request, $configuration);
            case 'camt':
                return $this->camtPostIndex($request, $configuration);

        }
    }

    /**
     * @param RolesPostRequest $request
     * @param Configuration    $configuration
     *
     * @return RedirectResponse
     * @throws ContainerExceptionInterface
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     */
    private function csvPostIndex(RolesPostRequest $request, Configuration $configuration): RedirectResponse
    {
        $data         = $request->getAllForFile();
        $needsMapping = $this->needMapping($data['do_mapping']);
        $configuration->setRoles($data['roles']);
        $configuration->setDoMapping($data['do_mapping']);

        session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());

        // then this is the new, full array:
        $fullArray = $configuration->toArray();

        // and it can be saved on disk:
        $configFileName = StorageService::storeArray($fullArray);
        app('log')->debug(sprintf('Old configuration was stored under key "%s".', session()->get(Constants::UPLOAD_CONFIG_FILE)));

        // this is a new config file name.
        session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);

        app('log')->debug(sprintf('New configuration is stored under key "%s".', session()->get(Constants::UPLOAD_CONFIG_FILE)));

        // set role config as complete.
        session()->put(Constants::ROLES_COMPLETE_INDICATOR, true);

        // redirect to mapping thing.
        if (true === $needsMapping) {
            return redirect()->route('006-mapping.index');
        }
        // otherwise, store empty mapping, and continue:
        // set map config as complete.
        session()->put(Constants::READY_FOR_CONVERSION, true);

        return redirect()->route('007-convert.index');
    }

    /**
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

    /**
     * @param Request       $request
     * @param Configuration $configuration
     *
     * @return View
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgument
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws UnableToProcessCsv
     */
    private function csvIndex(Request $request, Configuration $configuration): View
    {
        $mainTitle = 'Role definition';
        $subTitle  = 'Configure the role of each column in your file';

        // get columns from file
        $content  = StorageService::getContent(session()->get(Constants::UPLOAD_DATA_FILE), $configuration->isConversion());
        $columns  = RoleService::getColumns($content, $configuration);
        $examples = RoleService::getExampleData($content, $configuration);

        // submit mapping from config.
        $mapping = base64_encode(json_encode($configuration->getMapping(), JSON_THROW_ON_ERROR));

        // roles
        $roles = config('csv.import_roles');
        ksort($roles);

        // configuration (if it is set)
        $configuredRoles     = $configuration->getRoles();
        $configuredDoMapping = $configuration->getDoMapping();

        return view(
            'import.005-roles.index-csv',
            compact('mainTitle', 'configuration', 'subTitle', 'columns', 'examples', 'roles', 'configuredRoles', 'configuredDoMapping', 'mapping')
        );
    }

    /**
     * @param Request       $request
     * @param Configuration $configuration
     *
     * @return View
     */
    private function camtIndex(Request $request, Configuration $configuration): View
    {
        $mainTitle = 'Role definition';
        $subTitle  = 'Configure the role of each field in your camt.053 file';
        // TODO read some example data from the camt.053 file
        $roles = config('camt.import_roles'); // TODO maybe split up to different DataType -> Columns for Dates, Numbers, Strings -> AND OR create other roles for camt // list is imfported from csv.import_roles, without modification

        return view(
            'import.005-roles.index-camt',
            compact('mainTitle','subTitle','roles')
        );
    }

    /**
     * @param RolesPostRequest $request
     * @param Configuration    $configuration
     *
     * @return void
     */
    private function camtPostIndex(RolesPostRequest $request, Configuration $configuration)
    {
        die('not yet implemented.');
    }
}
