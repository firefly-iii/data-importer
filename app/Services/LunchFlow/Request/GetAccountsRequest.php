<?php

namespace App\Services\LunchFlow\Request;

use App\Exceptions\ImporterErrorException;
use App\Services\LunchFlow\Response\ErrorResponse;
use App\Services\LunchFlow\Response\GetAccountsResponse;
use App\Services\Shared\Response\Response;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class GetAccountsRequest extends Request {
    public string $connection;

    /**
     * ListConnectionsRequest constructor.
     */
    public function __construct(string $apiKey)
    {
        $this->setUrl('accounts');
        $this->setApiKey($apiKey);
        $this->setBase(config('lunchflow.api_url'));
    }

    /**
     * @throws GuzzleException
     */
    public function get(): Response
    {
        Log::debug('GetAccountsRequest::get()');

        try {
            $response = $this->authenticatedGet();
        } catch (ImporterErrorException $e) {
            // JSON thing.
            return new ErrorResponse($e->json ?? []);
        }
        return new GetAccountsResponse($response['accounts'] ?? []);
    }

    public function post(): Response
    {
        // Implement post() method.
    }

    public function put(): Response
    {
        // Implement put() method.
    }
}
