<?php
/*
 * UploadController.php
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
use App\Http\Middleware\UploadControllerMiddleware;
use App\Http\Request\UploadRequest;
use App\Services\Session\Constants;
use App\Services\Shared\Upload\UploadProcessor;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

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
        app('view')->share('pageTitle', 'Upload files');
        $this->middleware(UploadControllerMiddleware::class);
    }

    /**
     * @return Factory|View
     */
    public function index(Request $request)
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $mainTitle = 'Upload your file(s)';
        $subTitle  = 'Start page and instructions';
        $flow      = $request->cookie(Constants::FLOW_COOKIE);


        // get existing configs.
        $disk = Storage::disk('configurations');
        app('log')->debug(
            sprintf(
                'Going to check directory for config files: %s',
                config('filesystems.disks.configurations.root'),
            )
        );
        $all = $disk->files();

        // remove files from list
        $list    = [];
        $ignored = config('importer.ignored_files');
        foreach ($all as $entry) {
            if (!in_array($entry, $ignored, true)) {
                $list[] = $entry;
            }
        }

        app('log')->debug('List of files:', $list);

        return view('import.003-upload.index', compact('mainTitle', 'subTitle', 'list', 'flow'));
    }

    /**
     * @param UploadRequest $request
     *
     * @return RedirectResponse|Redirector
     * @throws ImporterErrorException
     */
    public function upload(UploadRequest $request)
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));

        $importableFiles = $request->file('importable_file');
        $configFiles     = $request->file('config_file');
        $oneConfig       = '1' === $request->get('one_config');
        $flow            = $request->cookie(Constants::FLOW_COOKIE);

        /** @var UploadProcessor $processor */
        $processor = app(UploadProcessor::class);
        $processor->setContent($importableFiles, $configFiles);
        $processor->setSingleConfiguration($oneConfig);
        $processor->setFlow($flow);
        $processor->setExistingConfiguration((string) $request->get('existing_config'));
        $processor->process();

        // collect array with upload info:
        $combinations = $processor->getCombinations();
        $errors       = $processor->getErrors();

        if ($errors->count() > 0) {
            return redirect(route('003-upload.index'))->withErrors($errors);
        }

        if ('nordigen' === $flow) {
            if (1 === count($combinations)) {
                session()->put(Constants::UPLOAD_CONFIG_FILE, $combinations[0]['config_name']);
            }
            // redirect to country + bank selector
            session()->put(Constants::HAS_UPLOAD, true);
            return redirect(route('009-selection.index'));
        }

        if ('spectre' === $flow) {
            if (1 === count($combinations)) {
                session()->put(Constants::UPLOAD_CONFIG_FILE, $combinations[0]['config_name']);
            }
            // redirect to spectre
            session()->put(Constants::HAS_UPLOAD, true);
            return redirect(route('011-connections.index'));
        }
        // for file processing:
        session()->put(Constants::UPLOADED_COMBINATIONS, $combinations);
        session()->put(Constants::SINGLE_CONFIGURATION_SESSION, $oneConfig || 1 === count($combinations));

        return redirect(route('004-configure.index'));
    }

}
