<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class BitacoraOperacionData
{
    public function __construct(
        public int $id,
        public ?int $usuarioId,
        public ?string $usuarioNombre,
        public ?string $usuarioEmail,
        public string $operacion,
        public ?string $tablaAfectada,
        public ?int $registroId,
        public ?string $descripcion,
        public string $fechaOperacion,
        // Quien operó sin cuenta, con código de aula.
        public ?string $colaborador = null,
        // El usuario de la cuenta afectada, cuando la tabla es `usuarios`.
        public ?string $registroEtiqueta = null,
    ) {}
}
