<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\RolGateway;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Support\Facades\DB;

final class EloquentRolGateway implements RolGateway
{
    public function asignarRolAUsuario(int $usuarioId, ?int $rolId): bool
    {
        $usuario = User::find($usuarioId);

        if (! $usuario instanceof User) {
            return false;
        }

        if ($rolId !== null && ! Role::where('id', $rolId)->exists()) {
            return false;
        }

        $usuario->forceFill(['role_id' => $rolId])->save();

        return true;
    }

    /**
     * @return list<string>
     */
    public function permisosDe(int $usuarioId): array
    {
        $claves = DB::table('usuarios as cuenta')
            ->join('permission_role as asignacion', 'asignacion.role_id', '=', 'cuenta.role_id')
            ->join('permissions as permiso', 'permiso.id', '=', 'asignacion.permission_id')
            ->where('cuenta.id', $usuarioId)
            // En el orden del catalogo (el de la matriz de permisos).
            ->orderBy('permiso.id')
            ->pluck('permiso.name');

        $permisos = [];

        foreach ($claves as $clave) {
            if (is_string($clave)) {
                $permisos[] = $clave;
            }
        }

        return $permisos;
    }
}
