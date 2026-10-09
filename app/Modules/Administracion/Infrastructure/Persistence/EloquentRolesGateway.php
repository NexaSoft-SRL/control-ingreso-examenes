<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\RolesGateway;
use App\Modules\Administracion\Application\DTOs\RolData;
use App\Modules\Administracion\Domain\Models\Permission;
use App\Modules\Administracion\Domain\Models\Role;
use Illuminate\Support\Facades\DB;

final class EloquentRolesGateway implements RolesGateway
{
    /**
     * @return list<RolData>
     */
    public function listar(): array
    {
        return $this->leer(null);
    }

    /**
     * @return array<string, string>
     */
    public function catalogo(): array
    {
        $catalogo = [];

        // El orden del catalogo es el de la matriz.
        foreach (DB::table('permissions')->orderBy('id')->get(['name', 'screen_name']) as $fila) {
            $datos = get_object_vars($fila);
            $clave = $datos['name'] ?? null;
            $pantalla = $datos['screen_name'] ?? null;

            if (is_string($clave) && $clave !== '') {
                $catalogo[$clave] = is_string($pantalla) && $pantalla !== '' ? $pantalla : $clave;
            }
        }

        return $catalogo;
    }

    public function buscar(int $rolId, bool $paraEscribir = false): ?RolData
    {
        if ($paraEscribir && ! Role::query()->whereKey($rolId)->lockForUpdate()->exists()) {
            return null;
        }

        return $this->leer($rolId)[0] ?? null;
    }

    public function nombreRegistrado(string $nombre, ?int $exceptoId = null): bool
    {
        $consulta = Role::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($nombre))]);

        if ($exceptoId !== null) {
            $consulta->whereKeyNot($exceptoId);
        }

        return $consulta->exists();
    }

    public function rolDeUsuario(int $usuarioId): ?int
    {
        $rolId = DB::table('usuarios')->where('id', $usuarioId)->value('role_id');

        return is_numeric($rolId) ? (int) $rolId : null;
    }

    /**
     * @param  list<string>  $permisos
     */
    public function crear(string $nombre, array $permisos): RolData
    {
        $rol = new Role;

        $rol->forceFill([
            'name' => trim($nombre),
            'es_sistema' => false,
        ])->save();

        $rol->permissions()->sync($this->identificadores($permisos));

        return $this->leer($rol->id)[0];
    }

    /**
     * @param  list<string>  $permisos
     */
    public function modificar(int $rolId, string $nombre, array $permisos): ?RolData
    {
        $rol = Role::find($rolId);

        if (! $rol instanceof Role) {
            return null;
        }

        $rol->forceFill(['name' => trim($nombre)])->save();
        $rol->permissions()->sync($this->identificadores($permisos));

        return $this->leer($rolId)[0] ?? null;
    }

    public function eliminar(int $rolId): bool
    {
        return Role::query()->whereKey($rolId)->delete() > 0;
    }

    /**
     * @param  list<string>  $claves
     * @return list<int>
     */
    private function identificadores(array $claves): array
    {
        $identificadores = [];

        foreach (Permission::query()->whereIn('name', $claves)->pluck('id') as $id) {
            if (is_numeric($id)) {
                $identificadores[] = (int) $id;
            }
        }

        return $identificadores;
    }

    /**
     * Los roles (o uno solo) con sus cuentas y sus permisos.
     *
     * @return list<RolData>
     */
    private function leer(?int $rolId): array
    {
        $consulta = DB::table('roles as rol')
            ->leftJoin('usuarios as cuenta', 'cuenta.role_id', '=', 'rol.id')
            ->groupBy('rol.id', 'rol.name', 'rol.es_sistema')
            ->orderByDesc('rol.es_sistema')
            ->orderBy('rol.id')
            ->selectRaw('rol.id, rol.name, rol.es_sistema, count(cuenta.id) as cuentas');

        $asignaciones = DB::table('permission_role as asignacion')
            ->join('permissions as permiso', 'permiso.id', '=', 'asignacion.permission_id')
            ->orderBy('permiso.id')
            ->select(['asignacion.role_id', 'permiso.name']);

        if ($rolId !== null) {
            $consulta->where('rol.id', $rolId);
            $asignaciones->where('asignacion.role_id', $rolId);
        }

        /** @var array<int, list<string>> $permisos */
        $permisos = [];

        foreach ($asignaciones->get() as $fila) {
            $datos = get_object_vars($fila);
            $clave = $datos['name'] ?? null;

            if (is_string($clave)) {
                $permisos[$this->entero($datos['role_id'] ?? null)][] = $clave;
            }
        }

        $roles = [];

        foreach ($consulta->get() as $fila) {
            $datos = get_object_vars($fila);
            $id = $this->entero($datos['id'] ?? null);
            $nombre = $datos['name'] ?? null;

            $roles[] = new RolData(
                id: $id,
                nombre: is_string($nombre) ? $nombre : '',
                esSistema: (bool) ($datos['es_sistema'] ?? false),
                cuentas: $this->entero($datos['cuentas'] ?? null),
                permisos: $permisos[$id] ?? [],
            );
        }

        return $roles;
    }

    private function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }
}
