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

    public function tienePermisoPuntoControl(int $usuarioId): bool
    {
        return DB::table('usuarios')
            ->join('permission_role', 'usuarios.role_id', '=', 'permission_role.role_id')
            ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
            ->where('usuarios.id', $usuarioId)
            ->where('permissions.name', 'punto_control')
            ->exists();
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
