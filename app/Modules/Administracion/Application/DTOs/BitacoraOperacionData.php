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
    ) {}
}
