<?php

/*
 * LinkController.php
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

namespace App\Http\Controllers\Import\EnableBanking;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Http\Controllers\Controller;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\EnableBanking\Model\Account;
use App\Services\EnableBanking\Request\PostAuthRequest;
use App\Services\EnableBanking\Request\PostSessionRequest;
use App\Services\EnableBanking\Response\AuthResponse;
use App\Services\EnableBanking\Response\SessionResponse;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Log;

/**
 * Class LinkController
 */
class LinkController extends Controller
{
    private ImportJobRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ImportJobRepository();
    }

    /**
     * Build the authorization URL and redirect user to Enable Banking.
     *
     * @throws ImporterHttpException
     */
    public function build(string $identifier): Redirector|RedirectResponse
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        $importJob = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();

        if ('' === $configuration->getEnableBankingBank()) {
            Log::debug('Return back to selection because bank is not set');

            return redirect()->route('eb-select-bank.index', [$identifier]);
        }

        $url = config('enablebanking.url');
        $callbackUrl = route('eb-connect.callback');

        Log::debug(sprintf('Enable Banking redirect URL: %s', $callbackUrl));

        $request = new PostAuthRequest($url);
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setAspsp($configuration->getEnableBankingBank());
        $request->setCountry($configuration->getEnableBankingCountry());
        $request->setState($identifier);
        $request->setRedirectUrl($callbackUrl);

        /** @var AuthResponse $response */
        $response = $request->post();

        Log::debug(sprintf('Got auth URL: %s', $response->url));
        Log::debug(sprintf('Auth ID: %s', $response->authId));

        // Save the auth ID for the callback
        $configuration->setEnableBankingAuthId($response->authId);
        $importJob->setConfiguration($configuration);
        $this->repository->saveToDisk($importJob);

        return redirect($response->url);
    }

    /**
     * Handle the callback from Enable Banking.
     *
     * @return Application|Redirector|RedirectResponse
     */
    public function callback(Request $request)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        $code = trim((string) $request->get('code'));
        $identifier = trim((string) $request->get('state'));
        Log::debug(sprintf('Authorization code: %s', $code));
        Log::debug(sprintf('State (identifier): %s', $identifier));

        if ('' === $code) {
            $error = $request->get('error', 'Unknown error');
            throw new ImporterHttpException(sprintf('Enable Banking authorization failed: %s', $error));
        }

        if ('' === $identifier) {
            throw new ImporterHttpException('Enable Banking callback missing state parameter');
        }

        $importJob = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();

        // Check if we already have a session and accounts (e.g., user refreshed the callback page)
        $existingSessions = $configuration->getEnableBankingSessions();
        $existingAccounts = $importJob->getServiceAccounts();
        if (count($existingSessions) > 0 && count($existingAccounts) > 0) {
            Log::debug('Session already exists with accounts, redirecting to configuration.');

            return redirect(route('configure-import.index', [$identifier]));
        }

        // Exchange the code for a session
        $url = config('enablebanking.url');
        $sessionRequest = new PostSessionRequest($url, $code);
        $sessionRequest->setTimeOut(config('importer.connection.timeout'));

        try {
            /** @var SessionResponse $sessionResponse */
            $sessionResponse = $sessionRequest->post();
        } catch (ImporterHttpException $e) {
            // Handle "already authorized" error gracefully
            if (str_contains($e->getMessage(), 'ALREADY_AUTHORIZED')) {
                Log::warning('Session already authorized, redirecting to configuration.');

                // If we have existing sessions, just redirect
                if (count($existingSessions) > 0) {
                    return redirect(route('configure-import.index', [$identifier]));
                }

                // Otherwise, redirect back to bank selection to start fresh
                return redirect(route('eb-select-bank.index', [$identifier]))
                    ->withErrors(['The authorization code has already been used. Please try connecting again.']);
            }
            throw $e;
        }

        Log::debug(sprintf('Got session ID: %s', $sessionResponse->sessionId));
        Log::debug(sprintf('Got %d accounts from session response', count($sessionResponse->accounts)));

        // Save the session ID
        $configuration->addEnableBankingSession($sessionResponse->sessionId);
        $importJob->setConfiguration($configuration);

        // Parse and save accounts from the session response
        $accounts = [];
        foreach ($sessionResponse->accounts as $accountData) {
            $accounts[] = Account::fromArray($accountData);
        }
        if (count($accounts) > 0) {
            Log::debug(sprintf('Saving %d accounts to import job', count($accounts)));
            $importJob->setServiceAccounts($accounts);
        }

        $importJob->setState('contains_content');
        $this->repository->saveToDisk($importJob);

        return redirect(route('configure-import.index', [$identifier]));
    }
}
