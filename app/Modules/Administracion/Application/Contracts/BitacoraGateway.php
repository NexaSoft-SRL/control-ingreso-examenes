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
        // Quien opero sin cuenta (estudiante, codigo de aula).
        ?string $actor = null,
    ): void;
}
