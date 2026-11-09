<?php

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\ResultadoAccesoData;
use App\Modules\Administracion\Application\DTOs\SesionData;

interface AuthenticationSecurityGateway
{
    /**
     * Comprueba el usuario y la contrasena, lleva la cuenta de intentos
     * fallidos y el bloqueo temporal, y rechaza la contrasena temporal
     * vencida. `$identifier` es el usuario tecleado.
     */
    public function authenticate(
        string $identifier,
        string $password,
        ?string $ipAddress,
        ?string $userAgent,
    ): ResultadoAccesoData;

    /**
     * Datos de la sesion de una cuenta activa, o null si la cuenta no
     * existe o esta bloqueada.
     */
    public function sesionDe(int $usuarioId): ?SesionData;
}
