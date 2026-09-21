<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

interface BitacoraGateway
{
    public function registrar(
        ?int $usuarioId,
        string $operacion,
        ?string $tablaAfectada,
        ?int $registroId,
        ?string $descripcion,
    ): void;
}
