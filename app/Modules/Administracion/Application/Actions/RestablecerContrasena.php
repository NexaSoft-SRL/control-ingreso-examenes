<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\RestablecimientoGateway;

final readonly class RestablecerContrasena
{
    public function __construct(
        private RestablecimientoGateway $restablecimiento,
        private BitacoraGateway $bitacora,
    ) {}

    public function execute(
        string $usuario,
        string $token,
        string $contrasenaNueva,
    ): bool {
        $usuarioId = $this->restablecimiento->restablecer(
            mb_strtolower(trim($usuario)),
            $token,
            $contrasenaNueva,
        );

        if ($usuarioId === null) {
            return false;
        }

        $this->bitacora->registrar(
            $usuarioId,
            'usuario.restablecer_contrasena',
            'users',
            $usuarioId,
            null,
        );

        return true;
    }
}
