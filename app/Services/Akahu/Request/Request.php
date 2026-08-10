<?php

declare(strict_types=1);

namespace App\Services\Akahu\Request;

use App\Services\Akahu\Authentication\SecretManager;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Client;

abstract class Request
{
    private string $appIdToken;
    private string $userAccessToken;
    private string $apiBaseUrl;
    private bool $debug;
    private array $queryParams = [];
    private array $historicRequests = [];
    private HandlerStack $handlerStack;
    private float $timeOut = 30.0;

    public function __construct()
    {
        $this->setDebug(false);
        $this->setAppIdToken(SecretManager::getAppIdToken());
        $this->setUserAccessToken(SecretManager::getUserAccessToken());
        $this->setApiBaseUrl(config('akahu.base_url'));

        if (config('app.debug')) {
            $this->createHandlerStack();
        }
    }

    public final function setAppIdToken(string $token): void
    {
        $this->appIdToken = $token;
    }

    public final function setUserAccessToken(string $token): void
    {
        $this->userAccessToken = $token;
    }

    public final function setApiBaseUrl(string $baseUrl): void
    {
        $this->apiBaseUrl = $baseUrl;
    }

    public final function setRequestTimeout(float $timeOut): void
    {
        $this->timeOut = $timeOut;
    }

    public final function setQueryParam(string $key, string $value): void
    {
        $this->queryParams[$key] = $value;
    }

    public final function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    private function createHandlerStack(): void
    {
        $this->handlerStack = HandlerStack::create();
        $this->handlerStack->push(Middleware::history($this->historicRequests));
    }

    public final function getHistoricRequests(): array
    {
        return $this->historicRequests;
    }

    protected final function getClient(): Client
    {
        return new Client([
            'connect_timeout' => $this->timeOut,
            'timeout' => $this->timeOut
        ]);
    }

    protected final function authenticatedGet(string $apiPath): array
    {
        $apiUrl = sprintf(
            "%s/%s",
            $this->apiBaseUrl,
            $apiPath,
        );

        $headers = [
            'Accept'        => 'application/json',
            'User-Agent'    => sprintf('FF3-data-importer/%s', config('importer.version')),
            'Authorization' => sprintf('Bearer %s', $this->userAccessToken),
            'X-Akahu-Id' => $this->appIdToken,
        ];

        $query = $this->queryParams;
        $debug = $this->debug;

        $handlerStackName = [];

        if (config('app.debug')) {
            $handler = $this->handlerStack;
            $handlerStackName = ['handler'];
        }

        $client = $this->getClient();

        try {
            $response = $client->request(
                'GET',
                $apiUrl,
                compact('headers', 'query', 'debug', $handlerStackName)
            );
        } catch (ConnectException|TooManyRedirectsException $e) {
            throw new ImporterErrorException(sprintf(
                'Failed to connect to the Akahu api (%s): %s',
                $apiUrl,
                $e->getMessage()
            ));
        } catch (BadResponseException $e) {
            throw new ImporterHttpException(sprintf(
                    'Akahu api returned an error response (%s): %s',
                    $apiUrl,
                    $e->getMessage()
                ),
                $e->getResponse()->getStatusCode(),
                $e
            );
        }

        if (config('app.debug')) {
            $historicRequests = $this->getHistoricRequests();
            $lastRequest = end($historicRequests)['request'];
            Log::debug(sprintf('Fetched from from endpoint "%s"', $lastRequest->getUri()));
        }

        $json = json_decode((string) $response->getBody(), true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $msg = sprintf(
                'Akahu api returned invalid json (%s). See logs for more details.',
                $apiUrl
            );

            Log::error($msg . ' json: "' . $response->getBody() . '"');

            throw new ImporterErrorException($msg);
        }

        return $json;
    }
}
