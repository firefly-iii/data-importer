<?php
declare(strict_types=1);


namespace App\Services\Shared\Authentication;

use App\Services\Enums\AuthenticationStatus;

interface AuthenticationValidatorInterface
{
    /**
     * @return AuthenticationStatus
     */
    public function validate(): AuthenticationStatus;

}
