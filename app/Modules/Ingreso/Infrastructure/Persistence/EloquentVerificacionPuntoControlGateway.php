<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\VerificacionPuntoControlGateway;
use Illuminate\Support\Facades\DB;

final class EloquentVerificacionPuntoControlGateway implements VerificacionPuntoControlGateway
{
    public function verificarAccesoPorToken(string $token, int $routeExamen, int $routeAula): array
    {
        /** @var object{id: int, examen_id: int, aula_id: int, codigo: string, nombre_colaborador: string, token: string, revocado_en: string|null}|null $acceso */
        $acceso = DB::table('accesos_codigo_aula')
            ->where('token', $token)
            ->whereNull('revocado_en')
            ->first();

        if ($acceso === null) {
            return ['estado' => 'no_valido'];
        }

        /** @var object{id: int, fecha: string, hora_inicio: string, duracion_minutos: int}|null $examen */
        $examen = DB::table('examenes')->where('id', $acceso->examen_id)->first();
        if ($examen === null) {
            return ['estado' => 'no_valido'];
        }

        $fecha = substr((string) $examen->fecha, 0, 10);
        $horaInicio = substr((string) $examen->hora_inicio, 0, 5);
        $duracion = (int) $examen->duracion_minutos;

        $inicioTs = strtotime("{$fecha} {$horaInicio}:00");
        $ventanaFin = ($inicioTs ?: 0) + ($duracion * 60);

        if (now()->timestamp > $ventanaFin) {
            DB::table('accesos_codigo_aula')
                ->where('id', $acceso->id)
                ->update(['revocado_en' => now()]);

            return ['estado' => 'finalizado'];
        }

        if ((int) $acceso->examen_id !== $routeExamen || (int) $acceso->aula_id !== $routeAula) {
            return ['estado' => 'aula_denegada'];
        }

        DB::table('accesos_codigo_aula')
            ->where('id', $acceso->id)
            ->update(['ultimo_acceso_en' => now()]);

        return [
            'estado' => 'ok',
            'acceso' => $acceso,
        ];
    }

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
                'acceso.revocado_en',
                'aula.nombre as aula',
                'examen.fecha',
                'examen.hora_inicio',
                'examen.duracion_minutos',
            ]);

        if ($fila === null) {
            return null;
        }

        $columnas = get_object_vars($fila);
        $texto = static fn (string $clave): string => is_scalar($columnas[$clave] ?? null) ? (string) $columnas[$clave] : '';
        $entero = static fn (string $clave): int => is_numeric($columnas[$clave] ?? null) ? (int) $columnas[$clave] : 0;
        $fin = VentanaDeAcceso::fin($texto('fecha'), $texto('hora_inicio'), $entero('duracion_minutos'));

        return [
            'id' => $entero('id'),
            'examen_id' => $entero('examen_id'),
            'aula_id' => $entero('aula_id'),
            'aula' => $texto('aula'),
            'nombre' => $texto('nombre_colaborador'),
            'hasta' => $fin->format('Y-m-d\TH:i:sP'),
            'estado' => match (true) {
                now()->greaterThan($fin) => 'finalizado',
                $columnas['revocado_en'] !== null => 'cortado',
                default => 'autorizado',
            },
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
