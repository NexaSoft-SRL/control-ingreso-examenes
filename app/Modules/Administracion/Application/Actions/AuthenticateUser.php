<?php

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AuthenticationSecurityGateway;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\DTOs\ResultadoAccesoData;

final readonly class AuthenticateUser
{
    public function __construct(
        private AuthenticationSecurityGateway $gateway,
        private BitacoraGateway $bitacora,
    ) {}

    /**
     * `$identifier` es el usuario tecleado. Nunca se asienta la contrasena.
     */
    public function execute(
        string $identifier,
        string $password,
        ?string $ipAddress,
        ?string $userAgent,
    ): ResultadoAccesoData {
        $identifier = mb_strtolower(trim($identifier));

        $resultado = $this->gateway->authenticate(
            $identifier,
            $password,
            $ipAddress,
            $userAgent,
        );

        if (! $resultado->esCorrecto()) {
            $this->bitacora->registrar(
                null,
                'sesion.intento_fallido',
                'users',
                $resultado->usuarioId,
                "Intento fallido con el usuario {$identifier}.",
            );

            return $resultado;
        }

        $this->bitacora->registrar(
            $resultado->usuarioId,
            'sesion.iniciar',
            'users',
            $resultado->usuarioId,
            null,
        );

        return $resultado;
    }
}
