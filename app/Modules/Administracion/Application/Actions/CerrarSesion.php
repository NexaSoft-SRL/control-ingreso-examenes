<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;

final readonly class CerrarSesion
{
    public function __construct(
        private BitacoraGateway $bitacora,
    ) {}

    public function execute(?int $usuarioId): void
    {
        $this->bitacora->registrar(
            $usuarioId,
            'sesion.cerrar',
            'users',
            $usuarioId,
            null,
        );
    }
}
