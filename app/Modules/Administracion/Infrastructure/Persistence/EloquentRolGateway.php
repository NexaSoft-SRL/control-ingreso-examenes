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
     * La vigencia vive en la tabla intermedia: se lee de ahi, sin pasar por
     * el pivote del modelo.
     *
     * @return list<string>
     */
    public function permisosVigentesDe(int $usuarioId): array
    {
        $usuario = User::find($usuarioId);

        if (! $usuario instanceof User) {
            return [];
        }

        $rolId = $usuario->getAttribute('role_id');

        if (! is_int($rolId)) {
            return [];
        }

        $filas = DB::table('permission_role as asignacion')
            ->join('permissions as permiso', 'permiso.id', '=', 'asignacion.permission_id')
            ->where('asignacion.role_id', $rolId)
            // En el orden del catalogo (el de la matriz de permisos).
            ->orderBy('permiso.id')
            ->select([
                'permiso.name',
                'asignacion.vigente_desde',
                'asignacion.vigente_hasta',
            ])
            ->get();

        $hoy = now()->toDateString();
        $vigentes = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $datos */
            $datos = get_object_vars($fila);

            $desde = $this->fecha($datos['vigente_desde'] ?? null);
            $hasta = $this->fecha($datos['vigente_hasta'] ?? null);

            if ($desde !== null && $desde > $hoy) {
                continue;
            }

            if ($hasta !== null && $hasta < $hoy) {
                continue;
            }

            if (is_string($datos['name'] ?? null)) {
                $vigentes[] = $datos['name'];
            }
        }

        return $vigentes;
    }

    /**
     * Las fechas de la tabla intermedia llegan como texto o como objeto
     * segun el motor; se normalizan a Y-m-d para comparar.
     */
    private function fecha(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_string($valor)) {
            return substr($valor, 0, 10);
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        return null;
    }
}
