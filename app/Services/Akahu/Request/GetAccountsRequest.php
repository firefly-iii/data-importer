<?php

declare(strict_types=1);

namespace App\Services\Akahu\Request;

use App\Exceptions\ImporterHttpException;
use App\Services\Akahu\Response\GetAccountsResponse;
use App\Services\Akahu\Model\Account\Account;
use App\Services\Shared\Response\Response;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

final class GetAccountsRequest extends Request
{
    public function get(): GetAccountsResponse
    {
        Log::debug(sprintf('GetAccountsRequest::%s()', __METHOD__));
        return new GetAccountsResponse($this->authenticatedGet('accounts'));
    }
}
