<?php

/*
 * TokenController.php
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

use App\Exceptions\ImporterErrorException;
use App\Services\Session\Constants;
use App\Services\Shared\Authentication\SecretManager;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Request\SystemInformationRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;

/**
 * Class TokenController
 */
class TokenController extends Controller
{
    /**
     * The user ends up here when they come back from Firefly III.
     *
     * @return Application|Factory|\Illuminate\Contracts\View\View|Redirector|RedirectResponse
     *
     * @throws ImporterErrorException
     * @throws GuzzleException
     * @throws \Throwable
     */
    public function callback(Request $request)
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $state        = (string)session()->pull('state');
        $codeVerifier = (string)$request->session()->pull('code_verifier');
        $clientId     = (int)$request->session()->pull('form_client_id');
        $baseURL      = (string)$request->session()->pull('form_base_url');
        $vanityURL    = (string)$request->session()->pull('form_vanity_url');
        $code         = $request->get('code');

        if ($state !== (string)$request->state) {
            app('log')->error(sprintf('State according to session: "%s"', $state));
            app('log')->error(sprintf('State returned in request : "%s"', $request->state));

            throw new ImporterErrorException('The "state" returned from your server doesn\'t match the state that was sent.');
        }
        // always POST to the base URL, never the vanity URL.
        $finalURL     = sprintf('%s/oauth/token', $baseURL);
        $params       = [
            'form_params' => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $clientId,
                'redirect_uri'  => route('token.callback'),
                'code_verifier' => $codeVerifier,
                'code'          => $code,
            ],
        ];
        app('log')->debug('State is valid!');
        app('log')->debug('Params for access token', $params);
        app('log')->debug(sprintf('Will contact "%s" for a token.', $finalURL));

        $opts         = [
            'verify'          => config('importer.connection.verify'),
            'connect_timeout' => config('importer.connection.timeout'),
            'headers'         => [
                'User-Agent' => sprintf('Mozilla/5.0 FF3-importer %s token callback', config('importer.version')),
            ],
        ];

        try {
            $response = (new Client($opts))->post($finalURL, $params);
        } catch (ClientException|RequestException $e) {
            $body = $e->getMessage();
            if ($e->hasResponse()) {
                $body = (string)$e->getResponse()->getBody();
                app('log')->error(sprintf('Client exception when decoding response: %s', $e->getMessage()));
                app('log')->error(sprintf('Response from server: "%s"', $body));
                // app('log')->error($e->getTraceAsString());
            }

            return view('error')->with('message', $e->getMessage())->with('body', $body);
        }

        try {
            $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            app('log')->error(sprintf('JSON exception when decoding response: %s', $e->getMessage()));
            app('log')->error(sprintf('Response from server: "%s"', (string)$response->getBody()));

            // app('log')->error($e->getTraceAsString());
            throw new ImporterErrorException(sprintf('JSON exception when decoding response: %s', $e->getMessage()));
        }
        app('log')->debug('Response', $data);

        SecretManager::saveAccessToken((string)$data['access_token']);
        SecretManager::saveBaseUrl($baseURL);
        SecretManager::saveVanityUrl($vanityURL);
        SecretManager::saveRefreshToken((string)$data['refresh_token']);
        app('log')->debug(sprintf('Return redirect  to "%s"', route('index')));

        return redirect(route('index'));
    }

    /**
     * This method will check if Firefly III accepts the access_token from the cookie
     * and the base URL (also from the cookie). The base_url is NEVER the vanity URL.§
     */
    public function doValidate(): JsonResponse
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $response        = ['result' => 'OK', 'message' => null];

        // Check if OAuth is configured but no session token exists
        $clientId        = (string)config('importer.client_id');
        $configToken     = (string)config('importer.access_token');
        // Corrected: Use the constant value directly with session helper
        $sessionHasToken = session()->has(Constants::SESSION_ACCESS_TOKEN) && '' !== session()->get(Constants::SESSION_ACCESS_TOKEN);

        if ('' !== $clientId && '' === $configToken && !$sessionHasToken) {
            app('log')->debug('OAuth configured but no session token - needs authentication');

            return response()->json(['result' => 'NEEDS_OAUTH', 'message' => 'OAuth authentication required']);
        }

        // get values from secret manager:
        $url             = SecretManager::getBaseUrl();
        $token           = SecretManager::getAccessToken();
        $infoRequest     = new SystemInformationRequest($url, $token);

        $infoRequest->setVerify(config('importer.connection.verify'));
        $infoRequest->setTimeOut(config('importer.connection.timeout'));

        app('log')->debug(sprintf('Now trying to authenticate with Firefly III at %s', $url));

        try {
            $result = $infoRequest->get();
        } catch (ApiHttpException $e) {
            app('log')->notice(sprintf('Could NOT authenticate with Firefly III at %s', $url));
            app('log')->error(sprintf('Could not connect to Firefly III: %s', $e->getMessage()));

            return response()->json(['result' => 'NOK', 'message' => $e->getMessage()]);
        }
        // -1 = OK (minimum is smaller)
        // 0 = OK (same version)
        // 1 = NOK (too low a version)

        $minimum         = (string)config('importer.minimum_version');
        $compare         = version_compare($minimum, $result->version);

        if (str_starts_with($result->version, 'develop')) {
            // overrule compare, because the user is running a develop version
            app('log')->warning(sprintf('You are connecting to a development version of Firefly III (%s). This may not work as expected.', $result->version));
            $compare = -1;
        }
        if (str_starts_with($result->version, 'branch')) {
            // overrule compare, because the user is running a branch version
            app('log')->warning(sprintf('You are connecting to a branch version of Firefly III (%s). This may not work as expected.', $result->version));
            $compare = -1;
        }

        if (str_starts_with($result->version, 'branch')) {
            // overrule compare, because the user is running a develop version
            app('log')->warning(sprintf('You are connecting to a branch version of Firefly III (%s). This may not work as expected.', $result->version));
            $compare = -1;
        }

        if (1 === $compare) {
            $errorMessage = sprintf('Your Firefly III version %s is below the minimum required version %s', $result->version, $minimum);
            app('log')->error(sprintf('Could not link to Firefly III: %s', $errorMessage));
            $response     = ['result' => 'NOK', 'message' => $errorMessage];
        }
        app('log')->debug('Result is', $response);

        return response()->json($response);
    }

    /**
     * Start page where the user will always end up on. Will do either of 3 things:
     *
     * 1. All info is present. Set some cookies and continue.
     * 2. Has client ID + URL. Will send user to Firefly III for permission.
     * 3. Has either 1 of those. Will show user some input form.
     *
     * @return Application|Factory|Redirector|RedirectResponse|View
     */
    public function index(Request $request)
    {
        $pageTitle   = 'Data importer';
        app('log')->debug(sprintf('Now at %s', __METHOD__));

        $accessToken = SecretManager::getAccessToken();
        $clientId    = SecretManager::getClientId();
        $baseUrl     = SecretManager::getBaseUrl();
        $vanityUrl   = SecretManager::getVanityUrl();

        app('log')->info('The following configuration information was found:');
        app('log')->info(sprintf('Personal Access Token: "%s" (limited to 25 chars if present)', substr($accessToken, 0, 25)));
        app('log')->info(sprintf('Client ID            : "%s"', $clientId));
        app('log')->info(sprintf('Base URL             : "%s"', $baseUrl));
        app('log')->info(sprintf('Vanity URL           : "%s"', $vanityUrl));

        // Option 1: access token and url are present:
        if ('' !== $accessToken && '' !== $baseUrl) {
            app('log')->debug(sprintf('Found personal access token + URL "%s" in config, set cookie and return to index.', $baseUrl));

            SecretManager::saveAccessToken($accessToken);
            SecretManager::saveBaseUrl($baseUrl);
            SecretManager::saveVanityUrl($vanityUrl);
            SecretManager::saveRefreshToken('');

            return redirect(route('index'));
        }

        // Option 2: client ID + base URL.
        if (0 !== $clientId && '' !== $baseUrl) {
            app('log')->debug(sprintf('Found client ID "%d" + URL "%s" in config, redirect to Firefly III for permission.', $clientId, $baseUrl));

            return $this->redirectForPermission($request, $baseUrl, $vanityUrl, $clientId);
        }

        // Option 3: either is empty, ask for client ID and/or base URL:
        $clientId    = 0 === $clientId ? '' : $clientId;

        // if the vanity url is the same as the base url, just give this view an empty string
        if ($vanityUrl === $baseUrl) {
            $vanityUrl = '';
        }

        return view('token.client_id', compact('baseUrl', 'vanityUrl', 'clientId', 'pageTitle'));
    }

    /**
     * This method forwards the user to Firefly III. Some parameters are stored in the user's session.
     */
    private function redirectForPermission(Request $request, string $baseURL, string $vanityURL, int $clientId): RedirectResponse
    {
        $baseURL               = rtrim($baseURL, '/');
        $vanityURL             = rtrim($vanityURL, '/');

        app('log')->debug(sprintf('Now in %s(request, "%s", "%s", %d)', __METHOD__, $baseURL, $vanityURL, $clientId));
        $state                 = \Str::random(40);
        $codeVerifier          = \Str::random(128);
        $request->session()->put('state', $state);
        $request->session()->put('code_verifier', $codeVerifier);
        $request->session()->put('form_client_id', $clientId);
        $request->session()->put('form_base_url', $baseURL);
        $request->session()->put('form_vanity_url', $vanityURL);

        $codeChallenge         = strtr(rtrim(base64_encode(hash('sha256', $codeVerifier, true)), '='), '+/', '-_');
        $params                = ['client_id' => $clientId, 'redirect_uri' => route('token.callback'), 'response_type' => 'code', 'scope' => '', 'state' => $state, 'code_challenge' => $codeChallenge, 'code_challenge_method' => 'S256'];
        $query                 = http_build_query($params);

        $oauthAuthorizeBaseUrl = $vanityURL;

        // Ensure $oauthAuthorizeBaseUrl is not empty before sprintf.
        // This is a fallback in case vanity_url (derived from VANITY_URL) is empty,
        // which would indicate a configuration problem.
        if ('' === $oauthAuthorizeBaseUrl) {
            $oauthAuthorizeBaseUrl = rtrim((string)config('importer.url'), '/');
        }

        $finalURL              = sprintf('%s/oauth/authorize?', $oauthAuthorizeBaseUrl);
        app('log')->debug('Query parameters are', $params);
        app('log')->debug(sprintf('Now redirecting to "%s" (params omitted)', $finalURL));

        return redirect($finalURL.$query);
    }

    /**
     * User submits the client ID + optionally the base URL.
     * Whatever happens, we redirect the user to Firefly III and beg for permission.
     *
     * @return Application|Redirector|RedirectResponse
     */
    public function submitClientId(Request $request)
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $data              = $request->validate(['client_id' => 'required|numeric|min:1|max:65536', 'base_url' => 'url']);
        app('log')->debug('Submitted data: ', $data);

        if (true === config('importer.expect_secure_url') && array_key_exists('base_url', $data) && !str_starts_with($data['base_url'], 'https://')) {
            $request->session()->flash('secure_url', 'URL must start with https://');

            return redirect(route('token.index'));
        }

        $data['client_id'] = (int)$data['client_id'];

        // grab base URL from config first, otherwise from submitted data:
        $baseURL           = config('importer.url');
        app('log')->debug(sprintf('[a] Base URL is "%s" (based on "FIREFLY_III_URL")', $baseURL));
        $vanityURL         = $baseURL;

        app('log')->debug(sprintf('[b] Vanity URL is now "%s" (based on "FIREFLY_III_URL")', $vanityURL));

        // if the config has a vanity URL it will always overrule.
        if ('' !== (string)config('importer.vanity_url')) {
            $vanityURL = config('importer.vanity_url');
            app('log')->debug(sprintf('[c] Vanity URL is now "%s" (based on "VANITY_URL")', $vanityURL));
        }

        // otherwise take base URL from the submitted data:
        if (array_key_exists('base_url', $data) && '' !== $data['base_url']) {
            $baseURL = $data['base_url'];
            app('log')->debug(sprintf('[d] Base URL is now "%s" (from POST data)', $baseURL));
        }
        if ('' === (string)$vanityURL) {
            $vanityURL = $baseURL;
            app('log')->debug(sprintf('[e] Vanity URL is now "%s" (from base URL)', $vanityURL));
        }

        // return request for permission:
        return $this->redirectForPermission($request, $baseURL, $vanityURL, $data['client_id']);
    }
}
