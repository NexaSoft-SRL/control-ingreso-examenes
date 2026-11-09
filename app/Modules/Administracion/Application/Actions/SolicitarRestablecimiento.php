<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\RestablecimientoGateway;

final readonly class SolicitarRestablecimiento
{
    public function __construct(
        private RestablecimientoGateway $restablecimiento,
        private BitacoraGateway $bitacora,
    ) {}

    /**
     * No informa si la cuenta existe ni si tiene correo: quien llama
     * responde siempre igual.
     */
    public function execute(string $usuario): void
    {
        $usuarioId = $this->restablecimiento->solicitar(
            mb_strtolower(trim($usuario)),
        );

        if ($usuarioId === null) {
            return;
        }

        $this->bitacora->registrar(
            $usuarioId,
            'usuario.solicitar_restablecimiento',
            'users',
            $usuarioId,
            null,
        );
    }
}
