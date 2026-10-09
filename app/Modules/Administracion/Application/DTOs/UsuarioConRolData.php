<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

/**
 * Una cuenta como la muestra la pantalla de usuarios.
 */
final readonly class UsuarioConRolData
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $usuario,
        public ?string $correo,
        public bool $activo,
        // Nombre del rol; null si la cuenta quedo sin rol.
        public ?string $rol,
    ) {}
}
