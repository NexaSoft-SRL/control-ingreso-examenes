<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\DTOs\CuentaCreadaData;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;

final readonly class CrearCuentaUsuario
{
    public function __construct(
        private CuentaUsuarioGateway $cuentas,
    ) {}

    /**
     * Devuelve la cuenta creada con su contrasena temporal, que se muestra
     * esta unica vez y no se registra en la bitacora. El asiento
     * `usuario.registrar` lo escribe el gateway dentro de la misma
     * transaccion que la cuenta.
     */
    public function execute(
        NuevaCuentaData $datos,
        ?int $administradorId,
    ): CuentaCreadaData {
        return $this->cuentas->crear($datos, $administradorId);
    }
}
