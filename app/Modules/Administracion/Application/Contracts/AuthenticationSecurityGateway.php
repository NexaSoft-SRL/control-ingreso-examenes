<?php

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\SesionData;
use App\Modules\Administracion\Domain\Models\User;

interface AuthenticationSecurityGateway
{
    public function authenticate(
        string $identifier,
        string $password,
        ?string $ipAddress,
        ?string $userAgent,
    ): ?User;

    /**
     * Datos de la sesion de una cuenta activa, o null si la cuenta no
     * existe o esta bloqueada.
     */
    public function sesionDe(int $usuarioId): ?SesionData;
}
