<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Authorization;

use App\Modules\Administracion\Domain\Models\Permission;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;

/**
 * HU-02: cada rol tiene su conjunto de permisos y un usuario solo opera
 * dentro de sus atribuciones. Aqui se responde una sola pregunta: ¿esta
 * cuenta puede usar esta pantalla?
 */
final class VerificarPermiso
{
    public function puede(User $usuario, string $permiso): bool
    {
        $rol = $usuario->role;

        if (! $rol instanceof Role) {
            return false;
        }

        return $rol->permissions
            ->contains(static fn (Permission $asignado): bool => $asignado->name === $permiso);
    }
}
