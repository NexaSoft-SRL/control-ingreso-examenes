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

    /**
     * HU-10: el docente lista ambientes para asignarlos a su examen, pero
     * no tiene el permiso de asignaturas. Se admite que la ruta acepte
     * uno de varios permisos separados por "|".
     *
     * @param  list<string>  $permisos
     */
    public function puedeAlguno(User $usuario, array $permisos): bool
    {
        foreach ($permisos as $permiso) {
            if ($this->puede($usuario, $permiso)) {
                return true;
            }
        }

        return false;
    }
}
