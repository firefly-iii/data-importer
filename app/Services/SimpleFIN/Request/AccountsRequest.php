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
use Illuminate\Support\Facades\Log;

/**
 * Class AccountsRequest
 */
final class AccountsRequest extends SimpleFINRequest
{
    /**
     * @throws ImporterHttpException
     */
    public function get(): AccountsResponse
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        // chunk time diff
        $chunks          = [];
        $params          = $this->getParameters();
        if (array_key_exists('start-date', $params) || array_key_exists('end-date', $params)) {
            Log::debug('Start date or end date are present, may need to chunk.');
            $start = $params['start-date'];
            $end   = $params['end-date'] ?? time();
            $diff  = $end - $start;
            Log::debug(sprintf('Difference is %d seconds, or %s', $diff, $this->formatTime($diff)));
            if ($diff > (90 * 24 * 60 * 60)) {
                Log::debug('More than 90 days, need to chunk this.');
                $chunks = $this->chunkByTime($start, $end);
            }
            if ($diff <= (90 * 24 * 60 * 60)) {
                Log::debug('No more than 90 days, no need to chunk this.');
                $chunk    = ['start-date' => $start];
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
        $accountResponse = null;
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
            $response = $this->authenticatedGet('/accounts');
            if (null !== $accountResponse) {
                Log::debug('Append to new account response.');
                // append to one.
                $newResponse = new AccountsResponse($response);
                $accountResponse->appendFromArray($newResponse->getAccounts());
            }
            if (null === $accountResponse) {
                Log::debug('Create new account response.');
                $accountResponse = new AccountsResponse($response);
            }
        }

        return $accountResponse;
    }

    private function formatTime(int $time): string
    {
        $return  = '';
        // days:
        $days    = floor($time / 86400);
        if ($days > 0) {
            $return .= sprintf('%dd', $days);
        }
        $time -= $days * 86400;

        $hours   = floor($time / 3600);
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
        $return       = [];
        $size         = 90 * 24 * 60 * 60;
        $currentStart = $start;
        Log::debug(sprintf('Start is %d (%s)', $start, Carbon::createFromTimestamp($start, config('app.timezone'))->toW3cString()));
        Log::debug(sprintf('End is   %d (%s)', $end, Carbon::createFromTimestamp($end, config('app.timezone'))->toW3cString()));
        while ($currentStart <= $end) {
            $currentEnd = $currentStart + $size;
            if ($currentEnd > $end) {
                $currentEnd = $end;
            }
            $return[]   = ['start-date' => $currentStart, 'end-date' => $currentEnd];
            Log::debug(sprintf('Add chunk on index #%d', count($return) - 1));
            Log::debug(sprintf('Start of chunk is %d (%s)', $currentStart, Carbon::createFromTimestamp($currentStart, config('app.timezone'))->toW3cString()));
            Log::debug(sprintf('End of chunk is   %d (%s)', $currentEnd, Carbon::createFromTimestamp($currentEnd, config('app.timezone'))->toW3cString()));

            $currentStart += $size;
        }

        return $return;
    }
}
