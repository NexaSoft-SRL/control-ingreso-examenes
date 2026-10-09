<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class CuentaActualizadaData
{
    public function __construct(
        public UsuarioConRolData $cuenta,
        // true = se desbloqueo, false = se bloqueo, null = el estado no cambio.
        public ?bool $estadoCambiadoA,
    ) {}
}
