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
use App\Services\Shared\Configuration\GetsConfigFromCombination;
use App\Services\Shared\Upload\GetsLocalConfigurations;
use App\Services\Shared\Upload\GetsUploadedFiles;
use App\Services\Shared\Upload\UploadProcessor;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;

/**
 * Class UploadController
 */
class UploadController extends Controller
{
    use GetsLocalConfigurations, GetsUploadedFiles, GetsConfigFromCombination;

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
        $list      = $this->getLocalConfigs();

        return view('import.003-upload.index', compact('mainTitle', 'subTitle', 'list'));
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

        $files          = $this->getFilesFromUpload($request);
        $singleConfig   = '1' === $request->get('one_config');
        $existingConfig = (string) $request->get('existing_config');


        /** @var UploadProcessor $processor */
        $processor = app(UploadProcessor::class);
        $processor->setUploadedFiles($files);
        $processor->setSingleConfiguration($singleConfig);
        $processor->setExistingConfiguration($existingConfig);
        $processor->process();


        // collect array with upload information:
        $combinations = $processor->getCombinations();
        $errors       = $processor->getErrors();

        if ($errors->count() > 0) {
            return redirect(route('003-upload.index'))->withErrors($errors);
        }

        // it depends on the config file what the next step is, but we haven't seen
        // the config up close yet. we may not want to risk parsing it in this step
        // (way too much complications)
        session()->put(Constants::UPLOADED_COMBINATIONS, $combinations);
        session()->put(Constants::SINGLE_CONFIGURATION_SESSION, $singleConfig || 1 === count($combinations));

        if(1 === count($combinations)) {
            // a single upload is easy to process.
            $configuration = $this->getConfigFromCombination($combinations[0]);
            $flow = $configuration->getFlow();

            if ('nordigen' === $flow) {
                // redirect to country + bank selector
                session()->put(Constants::HAS_UPLOAD, true);
                return redirect(route('009-selection.index'));
            }

            if ('spectre' === $flow) {
                // redirect to spectre
                session()->put(Constants::HAS_UPLOAD, true);
                return redirect(route('011-connections.index'));
            }
        }

        // if multiple configurations, always go to configure.
        return redirect(route('004-configure.index'));
    }


}
