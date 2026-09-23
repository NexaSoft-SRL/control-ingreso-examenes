<?php

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AuthenticationSecurityGateway;
use App\Modules\Administracion\Domain\Models\User;

final readonly class AuthenticateUser
{
    public function __construct(
        private AuthenticationSecurityGateway $gateway,
    ) {}

    public function execute(
        string $identifier,
        string $password,
        ?string $ipAddress,
        ?string $userAgent,
    ): ?User {
        return $this->gateway->authenticate(
            $identifier,
            $password,
            $ipAddress,
            $userAgent,
        );
    }
}
