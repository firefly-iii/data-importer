<?php
/*
 * StartImport.php
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

namespace App\Console;

use App\Exceptions\ImporterErrorException;
use App\Services\CSV\Configuration\Configuration;
use App\Services\CSV\Conversion\RoutineManager as CSVRoutineManager;
use App\Services\Shared\Conversion\ConversionStatus;
use App\Services\Shared\Conversion\RoutineStatusManager;
use App\Services\Shared\Import\Routine\RoutineManager;
use App\Services\Shared\Import\Status\SubmissionStatus;
use App\Services\Shared\Import\Status\SubmissionStatusManager;
use JsonException;
use Log;
use Storage;

/**
 * Trait StartImport
 */
trait StartImport
{
//    use ManageMessages;
//
//    protected array  $messages;
//    protected array  $warnings;
//    protected array  $errors;
//    protected string $identifier;
//

//
//    /**
//     * @param Configuration $configuration
//     * @param array         $transactions
//     * @return array
//     */
//    protected function startImport(Configuration $configuration, array $transactions): array
//    {
//        Log::debug(sprintf('Now at %s', __METHOD__));
//        $routine         = new RoutineManager($this->identifier);
//        $importJobStatus = SubmissionStatusManager::startOrFindSubmission($this->identifier);
//        $disk            = Storage::disk('jobs');
//        $fileName        = sprintf('%s.json', $this->identifier);
//
//        // get files from disk:
//        if (!$disk->has($fileName)) {
//            // TODO error in logs
//            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED, $this->identifier);
//            $message = sprintf('File "%s" not found, cannot continue.', $fileName);
//            $this->error($message);
//            SubmissionStatusManager::addError($this->identifier, 0, $message);
//            return [];
//        }
//
////
////        try {
////            $json = $disk->get($fileName);
////            $transactions = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
////            Log::debug(sprintf('Found %d transactions on the drive.', count($transactions)));
////        } catch (FileNotFoundException|JsonException $e) {
////            // TODO error in logs
////            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED);
////            return response()->json($importJobStatus->toArray());
////        }
////
////        $routine->setTransactions($transactions);
////
////        SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_RUNNING);
////
////        // then push stuff into the routine:
////        $routine->setConfiguration($configuration);
////        try {
////            $routine->start();
////        } catch (ImporterErrorException $e) {
////            Log::error($e->getMessage());
////            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED);
////            return response()->json($importJobStatus->toArray());
////        }
////
////        // set done:
////        SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_DONE);
////
////        // set config as complete.
////        session()->put(Constants::SUBMISSION_COMPLETE_INDICATOR, true);
////
//
////            // if configured, send report!
////            // TODO make event handler.
////            $log
////                = [
////                'messages' => $routine->getAllMessages(),
////                'warnings' => $routine->getAllWarnings(),
////                'errors'   => $routine->getAllErrors(),
////            ];
////
////            $send = config('mail.enable_mail_report');
////            Log::debug('Log log', $log);
////            if (true === $send) {
////                Log::debug('SEND MAIL');
////                Mail::to(config('mail.destination'))->send(new ImportFinished($log));
////            }
//
//
//        return response()->json($importJobStatus->toArray());
//    }
}
