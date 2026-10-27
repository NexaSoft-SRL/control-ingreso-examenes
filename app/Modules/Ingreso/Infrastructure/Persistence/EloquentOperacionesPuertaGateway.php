<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Contracts\OperacionesPuertaGateway;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoExcepcion;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentOperacionesPuertaGateway implements OperacionesPuertaGateway
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

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

                if (DB::table('incidencias')
                    ->where('ingreso_id', $ingresoId)
                    ->where('tipo', TipoIncidencia::Expulsion->value)
                    ->exists()) {
                    return false;
                }

                $columnas = get_object_vars($ingreso);
                $ahora = now();

                DB::table('incidencias')->insert([
                    'examen_id' => self::entero($columnas['examen_id'] ?? null),
                    'aula_id' => $aulaId,
                    'estudiante_id' => self::entero($columnas['estudiante_id'] ?? null),
                    'ingreso_id' => $ingresoId,
                    'tipo' => TipoIncidencia::Expulsion->value,
                    'origen' => OrigenIncidencia::Persona->value,
                    'detalle' => trim($motivo),
                    'ocurrida_en' => $ahora,
                    'reportada_por' => $usuarioId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
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
        $valor = DB::table('incidencias')
            ->where('ingreso_id', $ingresoId)
            ->where('tipo', TipoIncidencia::Expulsion->value)
            ->value('ocurrida_en');

        return is_string($valor) ? substr($valor, 11, 5) : null;
    }

    public function registrarExcepcion(
        int $examenId,
        int $aulaId,
        ?int $estudianteId,
        int $usuarioId,
        TipoExcepcion $tipo,
        string $motivo,
    ): ?array {
        $motivo = trim($motivo);
        $admitir = $tipo->admite();

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

                $ahora = now();
                // Lo que no es un cambio de aula lleva delante la situacion:
                // en el catalogo queda como «Otra» o «Intento rechazado».
                $incidenciaId = DB::table('incidencias')->insertGetId([
                    'examen_id' => $examenId,
                    'aula_id' => $aulaId,
                    'estudiante_id' => $estudianteId,
                    'ingreso_id' => $ingreso['id'] ?? null,
                    'tipo' => $tipo->tipoIncidencia()->value,
                    'origen' => OrigenIncidencia::Persona->value,
                    'detalle' => $admitir ? $motivo : $tipo->etiqueta().' · '.$motivo,
                    'ocurrida_en' => $ahora,
                    'reportada_por' => $usuarioId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                $this->bitacora->registrar(
                    $usuarioId,
                    'puerta.excepcion',
                    $ingreso === null ? 'incidencias' : 'ingresos',
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

    public function estudiantePerteneceAlExamen(int $examenId, int $estudianteId): bool
    {
        return DB::table('inscripciones as i')
            ->join('examen_grupo as eg', 'eg.grupo_id', '=', 'i.grupo_id')
            ->where('eg.examen_id', $examenId)
            ->where('i.estudiante_id', $estudianteId)
            ->exists();
    }

    private static function entero(mixed $valor): ?int
    {
        return is_numeric($valor) ? (int) $valor : null;
    }
}
