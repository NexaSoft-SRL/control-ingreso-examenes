<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AuthenticationSecurityGateway;
use App\Modules\Administracion\Application\DTOs\SesionData;

/**
 * La fuente de verdad de la sesion del cliente: quien es, que rol tiene y
 * a que pantallas llega hoy.
 */
final readonly class ConsultarSesion
{
    public function __construct(
        private AuthenticationSecurityGateway $gateway,
    ) {}

    public function execute(int $usuarioId): ?SesionData
    {
        return $this->gateway->sesionDe($usuarioId);
    }
}
