<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\OperacionesPuertaGateway;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentOperacionesPuertaGateway implements OperacionesPuertaGateway
{
    public function auditarIntentoNoResuelto(
        int $aulaId,
        int $usuarioId,
        string $tipo,
        string $identificador,
        bool $ambiguo,
    ): void {
        $clave = config('app.key');

        if (! is_string($clave) || $clave === '') {
            throw new RuntimeException('Clave de auditoría no configurada.');
        }

        DB::table('intentos_punto_control')->insert([
            'aula_id' => $aulaId,
            'registrado_por' => $usuarioId,
            'tipo_identificador' => $tipo,
            'identificador_sha256' => hash_hmac(
                'sha256',
                $identificador,
                $clave,
            ),
            'causa' => $ambiguo ? 'AMBIGUO' : 'SIN_EXAMEN',
            'registrado_en' => DB::raw('CURRENT_TIMESTAMP'),
        ]);
    }

    public function fechaIngreso(
        int $examenId,
        ?int $estudianteId,
    ): ?string {
        if ($estudianteId === null) {
            return null;
        }

        $fecha = DB::table('ingresos')
            ->where('examen_id', $examenId)
            ->where('estudiante_id', $estudianteId)
            ->orderByDesc('id')
            ->value('registrado_en');

        return is_string($fecha) ? $fecha : null;
    }

    public function examenDeIngreso(
        int $ingresoId,
        int $aulaId,
    ): ?int {
        $valor = DB::table('ingresos')
            ->where('id', $ingresoId)
            ->where('aula_id', $aulaId)
            ->value('examen_id');

        if (! is_int($valor) && ! is_string($valor)) {
            return null;
        }

        return (int) $valor;
    }

    public function expulsar(
        int $ingresoId,
        int $aulaId,
        int $usuarioId,
        string $motivo,
    ): bool {
        return DB::transaction(
            static function () use (
                $ingresoId,
                $aulaId,
                $usuarioId,
                $motivo,
            ): bool {
                $ingreso = DB::table('ingresos')
                    ->where('id', $ingresoId)
                    ->where('aula_id', $aulaId)
                    ->lockForUpdate()
                    ->first(['id']);

                if ($ingreso === null) {
                    return false;
                }

                if (DB::table('expulsiones')
                    ->where('ingreso_id', $ingresoId)
                    ->exists()) {
                    return false;
                }

                DB::table('expulsiones')->insert([
                    'ingreso_id' => $ingresoId,
                    'registrada_por' => $usuarioId,
                    'motivo' => trim($motivo),
                    'registrada_en' => DB::raw('CURRENT_TIMESTAMP'),
                ]);

                return true;
            },
        );
    }
}
