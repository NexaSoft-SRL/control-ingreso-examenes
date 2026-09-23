<?php

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Domain\Models\User;

interface AuthenticationSecurityGateway
{
    public function authenticate(
        string $identifier,
        string $password,
        ?string $ipAddress,
        ?string $userAgent,
    ): ?User;
}
