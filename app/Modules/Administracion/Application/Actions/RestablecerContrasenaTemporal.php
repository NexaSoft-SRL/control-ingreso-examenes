<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\DTOs\CuentaCreadaData;

/**
 * La administracion emite una contrasena temporal nueva para una cuenta
 * que la olvido o cuya temporal vencio. Se muestra esta unica vez; el
 * asiento `usuario.restablecer_temporal` lo escribe el gateway en la misma
 * transaccion.
 */
final readonly class RestablecerContrasenaTemporal
{
    public function __construct(
        private CuentaUsuarioGateway $cuentas,
    ) {}

    /**
     * Devuelve null si la cuenta no existe.
     */
    public function execute(int $usuarioId, ?int $administradorId): ?CuentaCreadaData
    {
        return $this->cuentas->emitirTemporal($usuarioId, $administradorId);
    }
}
