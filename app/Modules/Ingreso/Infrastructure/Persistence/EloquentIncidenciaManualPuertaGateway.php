<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciaManualPuertaGateway;
use App\Modules\Ingreso\Domain\Enums\TipoIncidenciaPuerta;
use Illuminate\Support\Facades\DB;

final class EloquentIncidenciaManualPuertaGateway implements IncidenciaManualPuertaGateway
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function recientes(int $examenId, int $aulaId, int $limite): array
    {
        $filas = DB::table('incidencias_aula as i')
            ->leftJoin('estudiantes as e', 'e.id', '=', 'i.estudiante_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'i.reportada_por')
            ->where('i.examen_id', $examenId)
            ->where('i.aula_id', $aulaId)
            ->orderByDesc('i.reportada_en')
            ->orderByDesc('i.id')
            ->limit($limite)
            ->get([
                'i.id',
                'i.examen_id',
                'i.aula_id',
                'i.estudiante_id',
                'e.codigo_universitario as codigo',
                'e.nombres',
                'e.apellidos',
                'i.tipo',
                'i.detalle',
                'i.reportada_por',
                'u.nombre as operador',
                'i.reportada_en',
                'i.atendida_por',
                'i.atendida_en',
            ]);

        $resultado = [];

        foreach ($filas as $fila) {
            /** @var object{
             * id: int,
             * examen_id: int,
             * aula_id: int,
             * estudiante_id: int|null,
             * codigo: string|null,
             * nombres: string|null,
             * apellidos: string|null,
             * tipo: string,
             * detalle: string,
             * reportada_por: int,
             * operador: string|null,
             * reportada_en: string,
             * atendida_por: int|null,
             * atendida_en: string|null
             * } $fila
             */
            $resultado[] = [
                'incidencia_id' => (int) $fila->id,
                'examen_id' => (int) $fila->examen_id,
                'aula_id' => (int) $fila->aula_id,
                'estudiante_id' => $fila->estudiante_id === null ? null : (int) $fila->estudiante_id,
                'codigo' => $fila->codigo,
                'nombres' => $fila->nombres,
                'apellidos' => $fila->apellidos,
                'tipo' => $fila->tipo,
                'tipo_etiqueta' => TipoIncidenciaPuerta::tryFrom($fila->tipo)?->etiqueta() ?? $fila->tipo,
                'detalle' => $fila->detalle,
                'reportada_por' => (int) $fila->reportada_por,
                'operador' => $fila->operador,
                'reportada_en' => (string) $fila->reportada_en,
                'hora' => substr((string) $fila->reportada_en, 11, 5),
                'atendida_por' => $fila->atendida_por === null ? null : (int) $fila->atendida_por,
                'atendida_en' => $fila->atendida_en,
            ];
        }

        return $resultado;
    }

    public function estudiantePorCodigoEnExamen(
        int $examenId,
        string $codigo,
    ): ?int {
        $id = DB::table('estudiantes as e')
            ->join('inscripciones as i', 'i.estudiante_id', '=', 'e.id')
            ->join('examen_grupo as eg', 'eg.grupo_id', '=', 'i.grupo_id')
            ->where('eg.examen_id', $examenId)
            ->where('e.codigo_universitario', $codigo)
            ->value('e.id');

        return is_int($id) ? $id : null;
    }

    public function estudiantePerteneceAlExamen(
        int $examenId,
        int $estudianteId,
    ): bool {
        return DB::table('inscripciones as i')
            ->join('examen_grupo as eg', 'eg.grupo_id', '=', 'i.grupo_id')
            ->where('eg.examen_id', $examenId)
            ->where('i.estudiante_id', $estudianteId)
            ->exists();
    }

    public function registrar(
        int $examenId,
        int $aulaId,
        ?int $estudianteId,
        int $usuarioId,
        string $tipo,
        string $detalle,
    ): int {
        return DB::transaction(function () use (
            $examenId,
            $aulaId,
            $estudianteId,
            $usuarioId,
            $tipo,
            $detalle,
        ): int {
            $id = DB::table('incidencias_aula')->insertGetId([
                'examen_id' => $examenId,
                'aula_id' => $aulaId,
                'estudiante_id' => $estudianteId,
                'reportada_por' => $usuarioId,
                'tipo' => trim($tipo),
                'detalle' => trim($detalle),
                'reportada_en' => DB::raw('CURRENT_TIMESTAMP'),
            ]);

            // N2: la incidencia queda en la bitacora con su autor.
            $this->bitacora->registrar(
                $usuarioId,
                'incidencia.registrar',
                'incidencias_aula',
                $id,
                implode(' · ', array_filter([
                    TipoIncidenciaPuerta::tryFrom(trim($tipo))?->etiqueta() ?? trim($tipo),
                    DescripcionPuerta::de($examenId, $aulaId, $estudianteId),
                ])),
            );

            return $id;
        });
    }
}
