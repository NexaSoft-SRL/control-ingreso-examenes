<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\VerificacionPuntoControlGateway;
use Illuminate\Support\Facades\DB;

final class EloquentVerificacionPuntoControlGateway implements VerificacionPuntoControlGateway
{
    public function accesoDeToken(string $token): ?array
    {
        $fila = DB::table('accesos_codigo_aula as acceso')
            ->join('examenes as examen', 'examen.id', '=', 'acceso.examen_id')
            ->join('aulas as aula', 'aula.id', '=', 'acceso.aula_id')
            ->where('acceso.token', $token)
            ->first([
                'acceso.id',
                'acceso.examen_id',
                'acceso.aula_id',
                'acceso.nombre_colaborador',
                'acceso.estado',
                'aula.nombre as aula',
                'examen.fecha',
                'examen.hora_inicio',
                'examen.duracion_minutos',
                'examen.finalizado_en',
            ]);

        if ($fila === null) {
            return null;
        }

        $columnas = get_object_vars($fila);
        $texto = static fn (string $clave): string => is_scalar($columnas[$clave] ?? null) ? (string) $columnas[$clave] : '';
        $entero = static fn (string $clave): int => is_numeric($columnas[$clave] ?? null) ? (int) $columnas[$clave] : 0;
        $finalizado = $texto('finalizado_en') === '' ? null : $texto('finalizado_en');
        $fin = VentanaDeAcceso::fin($texto('fecha'), $texto('hora_inicio'), $entero('duracion_minutos'), $finalizado);
        $cierre = VentanaDeAcceso::cierre($texto('fecha'), $finalizado);

        return [
            'id' => $entero('id'),
            'examen_id' => $entero('examen_id'),
            'aula_id' => $entero('aula_id'),
            'aula' => $texto('aula'),
            'nombre' => $texto('nombre_colaborador'),
            'hasta' => $fin->format('Y-m-d\TH:i:sP'),
            // Solo el autorizado opera la puerta; con el examen finalizado (o
            // terminado su dia), nadie.
            'estado' => now() >= $cierre ? 'finalizado' : mb_strtolower($texto('estado')),
        ];
    }

    public function tieneAlgunPermiso(int $usuarioId, array $permisos): bool
    {
        return DB::table('usuarios')
            ->join('permission_role', 'usuarios.role_id', '=', 'permission_role.role_id')
            ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
            ->where('usuarios.id', $usuarioId)
            ->whereIn('permissions.name', $permisos)
            ->exists();
    }

    public function tienePermisoPuntoControl(int $usuarioId): bool
    {
        return $this->tieneAlgunPermiso($usuarioId, ['punto_control']);
    }

    public function rolDelUsuario(int $usuarioId): string
    {
        $rol = DB::table('usuarios')
            ->join('roles', 'usuarios.role_id', '=', 'roles.id')
            ->where('usuarios.id', $usuarioId)
            ->value('roles.name');

        return is_string($rol) && $rol !== '' ? $rol : 'sin rol asignado';
    }
}
