<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class ActualizarCuentaData
{
    public function __construct(
        public int $usuarioId,
        public string $nombre,
        public string $usuario,
        public ?string $correo,
        public string $rol,
        // null = el estado no viaja en la peticion y no se toca.
        public ?bool $activo,
    ) {}
}
