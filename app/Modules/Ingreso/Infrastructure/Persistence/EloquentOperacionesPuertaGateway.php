<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Contracts\OperacionesPuertaGateway;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentOperacionesPuertaGateway implements OperacionesPuertaGateway
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

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
            function () use (
                $ingresoId,
                $aulaId,
                $usuarioId,
                $motivo,
            ): bool {
                $ingreso = DB::table('ingresos')
                    ->where('id', $ingresoId)
                    ->where('aula_id', $aulaId)
                    ->lockForUpdate()
                    ->first(['id', 'examen_id', 'estudiante_id']);

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

                // N2: la expulsion queda en la bitacora con su autor.
                $this->bitacora->registrar(
                    $usuarioId,
                    'puerta.expulsion',
                    'ingresos',
                    $ingresoId,
                    DescripcionPuerta::de(
                        self::entero(get_object_vars($ingreso)['examen_id'] ?? null),
                        $aulaId,
                        self::entero(get_object_vars($ingreso)['estudiante_id'] ?? null),
                    ).' · Motivo: '.trim($motivo),
                );

                return true;
            },
        );
    }

    public function horaExpulsion(int $ingresoId): ?string
    {
        $valor = DB::table('expulsiones')
            ->where('ingreso_id', $ingresoId)
            ->value('registrada_en');

        return is_string($valor) ? substr($valor, 11, 5) : null;
    }

    public function registrarExcepcion(
        int $examenId,
        int $aulaId,
        ?int $estudianteId,
        int $usuarioId,
        string $tipo,
        string $motivo,
        bool $admitir,
    ): ?array {
        $motivo = trim($motivo);

        try {
            return DB::transaction(function () use (
                $examenId,
                $aulaId,
                $estudianteId,
                $usuarioId,
                $tipo,
                $motivo,
                $admitir,
            ): array {
                $ingreso = null;

                if ($admitir && $estudianteId !== null) {
                    $ingresoId = DB::table('ingresos')->insertGetId([
                        'examen_id' => $examenId,
                        'estudiante_id' => $estudianteId,
                        'aula_id' => $aulaId,
                        'registrado_por' => $usuarioId,
                        'es_excepcion' => true,
                        'motivo_excepcion' => $motivo,
                        'registrado_en' => DB::raw('CURRENT_TIMESTAMP'),
                    ]);

                    $registrado = DB::table('ingresos')->where('id', $ingresoId)->value('registrado_en');
                    $ingreso = [
                        'id' => $ingresoId,
                        'hora' => is_string($registrado) ? substr($registrado, 11, 5) : '',
                    ];
                }

                $incidenciaId = DB::table('incidencias_aula')->insertGetId([
                    'examen_id' => $examenId,
                    'aula_id' => $aulaId,
                    'estudiante_id' => $estudianteId,
                    'reportada_por' => $usuarioId,
                    'tipo' => $tipo,
                    'detalle' => $motivo,
                    'reportada_en' => DB::raw('CURRENT_TIMESTAMP'),
                ]);

                $this->bitacora->registrar(
                    $usuarioId,
                    'puerta.excepcion',
                    $ingreso === null ? 'incidencias_aula' : 'ingresos',
                    $ingreso === null ? $incidenciaId : $ingreso['id'],
                    DescripcionPuerta::de($examenId, $aulaId, $estudianteId)
                        .($ingreso === null ? '' : ' · Admitido como excepción')
                        .' · Motivo: '.$motivo,
                );

                return [
                    'incidencia_id' => $incidenciaId,
                    'ingreso' => $ingreso,
                ];
            });
        } catch (QueryException $error) {
            $mensaje = $error->errorInfo[2] ?? null;

            if (
                ($error->errorInfo[0] ?? null) === '23505'
                && is_string($mensaje)
                && str_contains($mensaje, 'uq_ingreso_examen_estudiante')
            ) {
                return null;
            }

            throw $error;
        }
    }

    private static function entero(mixed $valor): ?int
    {
        return is_numeric($valor) ? (int) $valor : null;
    }
}
