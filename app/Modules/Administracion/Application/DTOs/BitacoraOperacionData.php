<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class BitacoraOperacionData
{
    public function __construct(
        public int $id,
        // null = lo hizo el sistema (o la cuenta ya no existe).
        public ?string $usuario,
        public ?string $rol,
        public string $operacion,
        public ?string $descripcion,
        // Instante ISO 8601 con zona.
        public string $fecha,
    ) {}
}
