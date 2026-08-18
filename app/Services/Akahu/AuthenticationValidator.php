<?php

declare(strict_types=1);

namespace App\Services\Akahu;

use App\Services\Akahu\Authentication\SecretManager;
use App\Services\Enums\AuthenticationStatus;
use App\Services\Shared\Authentication\AuthenticationValidatorInterface;
use Illuminate\Support\Facades\Log;

final class AuthenticationValidator implements AuthenticationValidatorInterface
{
    public function validate(): AuthenticationStatus
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        if (!SecretManager::hasAppIdToken() || !SecretManager::hasUserAccessToken()) {
            return AuthenticationStatus::NODATA;
        }

        // If were in a reauthentication flow then return NODATA to let the
        // user reauthenticate.
        if (session()->has('akahu_reauthenticate')) {
            if ('true' === session()->get('akahu_reauthenticate')) {
                return AuthenticationStatus::NODATA;
            }
        }

        return AuthenticationStatus::AUTHENTICATED;
    }

    public function getData(): array
    {
        return SecretManager::getSecrets();
    }

    public function setData(array $data): void
    {
        // Clear the reauthentication flow flag whenever we set new data
        session()->forget('akahu_reauthenticate');

        SecretManager::setSecrets($data);
    }
}
