<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\ConsultaPermisosGateway;
use Illuminate\Support\Facades\DB;

final class EloquentConsultaPermisosGateway implements ConsultaPermisosGateway
{
    public function tienePermiso(int $usuarioId, string $permiso): bool
    {
        return DB::table('usuarios as usuario')
            ->join('permission_role as asignacion', 'asignacion.role_id', '=', 'usuario.role_id')
            ->join('permissions as permiso', 'permiso.id', '=', 'asignacion.permission_id')
            ->where('usuario.id', $usuarioId)
            ->where('permiso.name', $permiso)
            ->exists();
    }
}
