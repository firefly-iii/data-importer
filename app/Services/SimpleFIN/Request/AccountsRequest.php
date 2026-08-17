<?php

/*
 * AccountsRequest.php
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

namespace App\Services\SimpleFIN\Request;

use App\Exceptions\ImporterHttpException;
use App\Services\SimpleFIN\Response\AccountsResponse;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Safe\Exceptions\FilesystemException;
use function Safe\file_get_contents;
use function Safe\file_put_contents;

/**
 * Class AccountsRequest
 */
final class AccountsRequest extends SimpleFINRequest
{
    private string $fakeDataPath = '';

    public function __construct()
    {
        $this->fakeDataPath = storage_path('fake-data/simplefin-accounts-%s.json');
    }

    /**
     * @throws ImporterHttpException
     */
    public function get(): AccountsResponse
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        // chunk time diff
        $chunkSize = config('simplefin.max_chunk_size');
        $chunks    = [];
        $params    = $this->getParameters();
        if (array_key_exists('start-date', $params) || array_key_exists('end-date', $params)) {
            Log::debug('Start date or end date are present, may need to chunk.');
            $start = $params['start-date'];
            $end   = $params['end-date'] ?? time();
            $diff  = $end - $start;
            Log::debug(sprintf('Difference is %d seconds, or %s', $diff, $this->formatTime($diff)));
            if ($diff > ($chunkSize * 24 * 60 * 60)) {
                Log::debug(sprintf('More than %d days, need to chunk this.', $chunkSize));
                $chunks = $this->chunkByTime($start, $end);
            }
            if ($diff <= ($chunkSize * 24 * 60 * 60)) {
                Log::debug(sprintf('No more than %d days, no need to chunk this.', $chunkSize));
                $chunk = ['start-date' => $start];
                if (array_key_exists('end-date', $params)) {
                    $chunk['end-date'] = $params['end-date'];
                }
                $chunks[] = $chunk;
            }
        }
        if (!array_key_exists('start-date', $params) && !array_key_exists('end-date', $params)) {
            // add empty array to chunks.
            $chunks[] = [];
        }
        $accountResponse  = null;
        $emptyChunkStreak = 0;
        Log::debug(sprintf('Collected %d chunks(s)', count($chunks)));
        foreach ($chunks as $index => $chunk) {
            Log::debug(sprintf('Chunk #%d', $index + 1), $chunk);

            if (array_key_exists('start-date', $chunk)) {
                $params['start-date'] = $chunk['start-date'];
                Log::debug(sprintf('Chunk #%d has start-date %d', $index + 1, $chunk['start-date']));
            }
            if (!array_key_exists('start-date', $chunk)) {
                Log::debug(sprintf('Chunk #%d has NO start-date.', $index + 1));
            }

            if (array_key_exists('end-date', $chunk)) {
                $params['end-date'] = $chunk['end-date'];
                Log::debug(sprintf('Chunk #%d has end-date %d', $index + 1, $chunk['end-date']));
            }
            if (!array_key_exists('end-date', $chunk)) {
                Log::debug(sprintf('Chunk #%d has NO end-date.', $index + 1));
            }
            $this->setParameters($params);
            $hash       = $this->getParameterHash();
            $grabFake   = (bool)config('importer.fake_data');
            $fakeExists = file_exists(sprintf($this->fakeDataPath, $hash));
            // grab fake data at this point if necessary.
            $response = null;
            if ($grabFake && $fakeExists) {
                Log::debug('Will collect fake data instead of real data.');
                $content = null;

                try {
                    $content = file_get_contents(sprintf($this->fakeDataPath, $hash));
                } catch (FilesystemException $e) {
                    Log::error(sprintf('Could not read fake data: %s', $e->getMessage()));
                }
                if (null !== $content) {
                    $response = new Response(200, [], $content);
                }
            }
            if (!$grabFake || !$fakeExists) {
                $response = $this->authenticatedGet('/accounts');
            }
            $body = '';
            if (null !== $response) {
                $body = (string)$response->getBody();
            }
            // need to make a new response anyway. Also log a count.
            $newResponse           = new AccountsResponse($response);
            $chunkTransactionCount = 0;
            foreach ($newResponse->getAccounts() as $chunkAccount) {
                $chunkTransactionCount += count($chunkAccount->transactions);
            }
            Log::debug(sprintf('Chunk #%d returned %d transaction(s).', $index + 1, $chunkTransactionCount));

            if (null !== $accountResponse) {
                Log::debug('Append to new account response.');
                // append to one.
                $accountResponse->appendFromArray($newResponse->getAccounts());
            }
            if (null === $accountResponse) {
                Log::debug('Create new account response.');
                $accountResponse = $newResponse;
                unset($newResponse);
            }

            // store fake data in new thing:
            if ($grabFake && !$fakeExists && true === (bool)config('importer.store_fake_data')) {
                Log::debug('Will store this run as fake data to use the next time.');

                try {
                    file_put_contents(sprintf($this->fakeDataPath, $hash), $body);
                } catch (FilesystemException $e) {
                    Log::error(sprintf('Could not store fake data: %s', $e->getMessage()));
                }
            }
            // check if response was empty and if so, stop.
            $emptyChunkStreak = 0 === $chunkTransactionCount ? $emptyChunkStreak + 1 : 0;
            if (count($chunks) > 1 && $emptyChunkStreak >= 2) {
                Log::debug(sprintf('Stopping early because more than %d days with no transactions to save SimpleFIN API requests.', $emptyChunkStreak * $chunkSize));
                break;
            }
        }

        return $accountResponse;
    }

    private function formatTime(int $time): string
    {
        $return = '';
        // days:
        $days = floor($time / 86_400);
        if ($days > 0) {
            $return .= sprintf('%dd', $days);
        }
        $time -= $days * 86_400;

        $hours = floor($time / 3600);
        if ($hours > 0) {
            $return .= sprintf('%dh', $hours);
        }
        $time    -= $hours * 3600;
        $minutes = floor($time / 60);
        if ($minutes > 0) {
            $return .= sprintf('%dm', $minutes);
        }
        $time    -= $minutes * 60;
        $seconds = $time % 60;
        if ($seconds > 0) {
            $return .= sprintf('%ds', $seconds);
        }

        return $return;
    }

    private function chunkByTime(int $start, int $end): array
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $return     = [];
        $chunkSize  = config('simplefin.max_chunk_size');
        $size       = $chunkSize * 24 * 60 * 60;
        $currentEnd = $end;
        Log::debug(sprintf('Start is %d (%s)', $start, Carbon::createFromTimestamp($start, config('app.timezone'))->toW3cString()));
        Log::debug(sprintf('End is   %d (%s)', $end, Carbon::createFromTimestamp($end, config('app.timezone'))->toW3cString()));

        // count backwards, not forwards.
        while ($currentEnd > $start) {
            $currentStart = $currentEnd - $size;
            if ($currentStart < $start) {
                $currentStart = $start;
            }
            $return[] = ['start-date' => $currentStart, 'end-date' => $currentEnd];
            Log::debug(sprintf('Add chunk on index #%d', count($return) - 1));
            Log::debug(sprintf('Start of chunk is %d (%s)', $currentStart, Carbon::createFromTimestamp($currentStart, config('app.timezone'))->toW3cString()));
            Log::debug(sprintf('End of chunk is   %d (%s)', $currentEnd, Carbon::createFromTimestamp($currentEnd, config('app.timezone'))->toW3cString()));

            $currentEnd -= $size;
        }

        return $return;
    }
}
