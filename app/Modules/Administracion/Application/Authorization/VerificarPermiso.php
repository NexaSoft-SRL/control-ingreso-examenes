<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Authorization;

use App\Modules\Administracion\Application\Contracts\RolGateway;

/**
 * Responde si el usuario puede entrar a una pantalla, segun los permisos
 * vigentes del rol que tenga asignado (HU-02).
 */
final readonly class VerificarPermiso
{
    public function __construct(
        private RolGateway $roles,
    ) {}

    public function puede(int $usuarioId, string $permiso): bool
    {
        return in_array(
            $permiso,
            $this->roles->permisosVigentesDe($usuarioId),
            true,
        );
    }
}
