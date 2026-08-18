<?php

declare(strict_types=1);

namespace App\Services\Akahu\Authentication;

final class SecretManager
{
    public const string APP_ID_TOKEN = 'app_id_token';
    public const string USER_ACCESS_TOKEN = 'user_access_token';

    public static function getAppIdToken(): string
    {
        if (session()->exists(self::APP_ID_TOKEN)) {
            return session()->get(self::APP_ID_TOKEN);
        }

        return config(sprintf('akahu.%s', self::APP_ID_TOKEN));
    }

    public static function getUserAccessToken(): string
    {
        if (session()->exists(self::USER_ACCESS_TOKEN)) {
            return session()->get(self::USER_ACCESS_TOKEN);
        }

        return config(sprintf('akahu.%s', self::USER_ACCESS_TOKEN));
    }

    public static function setAppIdToken(#[\SensitiveParameter] string $token): void
    {
        session()->put(self::APP_ID_TOKEN, $token);
    }

    public static function setUserAccessToken(#[\SensitiveParameter] string $token): void
    {
        session()->put(self::USER_ACCESS_TOKEN, $token);
    }

    public static function hasAppIdToken(): bool
    {
        return '' !== self::getAppIdToken();
    }

    public static function hasUserAccessToken(): bool
    {
        return '' !== self::getUserAccessToken();
    }

    public static function getSecrets(): array
    {
        return [
            self::APP_ID_TOKEN => SecretManager::getAppIdToken(),
            self::USER_ACCESS_TOKEN => SecretManager::getUserAccessToken(),
        ];
    }

    public static function setSecrets(array $data): void
    {
        SecretManager::setAppIdToken($data[self::APP_ID_TOKEN]);
        SecretManager::setUserAccessToken($data[self::USER_ACCESS_TOKEN]);
    }
}
