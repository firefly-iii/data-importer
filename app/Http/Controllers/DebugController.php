<?php

/*
 * DebugController.php
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

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Exception;

class DebugController extends Controller
{
    /**
     * Show debug info.
     *
     * @return Factory|View
     */
    public function index(Request $request)
    {
        $now        = Carbon::now()->format('Y-m-d H:i:s e');
        $table      = $this->getTable();

        /** @var Logger $logger */
        $logger     = Log::driver();
        $handlers   = $logger->getHandlers();
        $logContent = '';
        foreach ($handlers as $handler) {
            if ($handler instanceof RotatingFileHandler) {
                $logFile = $handler->getUrl();
                if (null !== $logFile) {
                    try {
                        $logContent = (string)file_get_contents($logFile);
                    } catch (Exception) {
                        // @ignoreException
                    }
                }
            }
        }
        if ('' !== $logContent) {
            // last few lines
            $logContent = sprintf('Truncated from this point <----|%s', substr($logContent, -32 * 1024));
        }
        if (true === config('importer.is_external')) {
            $logContent = 'No logs, external installation.';
        }

        Log::emergency('I am a EMERGENCY message.');
        Log::alert('I am a ALERT message.');
        Log::critical('I am a CRITICAL message.');
        Log::error('I am a ERROR message.');
        Log::warning('I am a WARNING message.');
        Log::notice('I am a NOTICE message.');
        Log::info(sprintf('[%s] I am a INFO message.', config('importer.version')));
        Log::debug('I am a DEBUG message.');

        return view(
            'debug',
            compact(
                'now',
                'table',
                'logContent',
            )
        );
    }

    private function getTable(): string
    {
        $system = $this->getSystemInfo();
        $app    = $this->getAppInfo();
        $user   = $this->getUserInfo();
        $table  = view('debug-table', compact('system', 'app', 'user'))->render();

        return str_replace(["\n", "\t", '  '], '', $table);
    }

    private function getSystemInfo(): array
    {
        $build     = null;
        $baseBuild = null;
        $isDocker  = config('importer.docker.is_docker', false);

        if (true === $isDocker) {
            try {
                if (file_exists('/var/www/counter-main.txt')) {
                    $build = trim((string) file_get_contents('/var/www/counter-main.txt'));
                }
            } catch (Exception $e) {
                Log::debug('Could not check build counter, but that\'s ok.');
                Log::warning($e->getMessage());
            }
            if ('' !== (string)config('importer.docker.base_build')) {
                $baseBuild = (string)config('importer.docker.base_build');
            }
        }
        $search    = ['~', '#'];
        $replace   = ['\~', '# '];

        return [
            'is_docker'   => $isDocker,
            'build'       => $build,
            'base_build'  => $baseBuild,
            'php_version' => str_replace($search, $replace, PHP_VERSION),
            'php_os'      => str_replace($search, $replace, PHP_OS),
            'interface'   => \PHP_SAPI,
        ];
    }

    private function getAppInfo(): array
    {
        return [
            'debug'          => var_export(config('app.debug'), true),
            'display_errors' => ini_get('display_errors'),
            'reporting'      => $this->errorReporting((int)ini_get('error_reporting')),
            'bcscale'        => bcscale(),
        ];
    }

    /**
     * Some common combinations.
     */
    protected function errorReporting(int $value): string // get configuration
    {
        $array = [
            -1                                                             => 'ALL errors',
            E_ALL & ~E_NOTICE & ~E_DEPRECATED                              => 'E_ALL & ~E_NOTICE & ~E_DEPRECATED',
            E_ALL                                                          => 'E_ALL',
            E_ALL & ~E_DEPRECATED                                          => 'E_ALL & ~E_DEPRECATED',
            E_ALL & ~E_NOTICE                                              => 'E_ALL & ~E_NOTICE',
            E_ALL & ~E_NOTICE                                              => 'E_ALL & ~E_NOTICE',
            E_COMPILE_ERROR | E_RECOVERABLE_ERROR | E_ERROR | E_CORE_ERROR => 'E_COMPILE_ERROR|E_RECOVERABLE_ERROR|E_ERROR|E_CORE_ERROR',
        ];

        return $array[$value] ?? sprintf('flags: %s', (string)$value);
    }

    private function getUserInfo(): array
    {
        return [
            'user_agent' => request()->header('user-agent'),
        ];
    }
}
