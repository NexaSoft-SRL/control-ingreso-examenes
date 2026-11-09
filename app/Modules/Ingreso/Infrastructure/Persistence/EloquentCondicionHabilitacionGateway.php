<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\CondicionHabilitacionGateway;
use App\Modules\Ingreso\Application\DTOs\CondicionData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * `habilitaciones` es de otro modulo: se lee por nombre de tabla. Vencido el
 * plazo general de fotos, el inscrito que no subio la suya no pasa, aunque
 * su lista no se haya vuelto a abrir desde que vencio; y la inhabilitacion
 * que puso el sistema por la foto ya no vale si despues la subio.
 */
final class EloquentCondicionHabilitacionGateway implements CondicionHabilitacionGateway
{
    use LeeFilas;

    public function condicionDe(int $examenId, int $estudianteId): ?CondicionData
    {
        // El plazo general de fotos que fijo la administracion (el ultimo).
        $plazo = DB::table('plazos_foto as plazo')
            ->leftJoin('users as autor', 'autor.id', '=', 'plazo.fijado_por')
            ->orderByDesc('plazo.id')
            ->first(['plazo.hasta', 'autor.name as autor']);
        $vencido = $plazo === null ? null : $this->columnas($plazo);
        $hasta = $vencido === null ? null : $this->texto($vencido['hasta'] ?? null);

        if ($hasta !== null && substr($hasta, 0, 10) < now()->toDateString() && $this->sinFoto($examenId, $estudianteId)) {
            return new CondicionData(
                habilitado: false,
                aulaId: null,
                motivo: 'No subió su foto',
                registradaPor: $this->texto($vencido['autor'] ?? null),
                fecha: substr($hasta, 0, 10),
            );
        }

        $fila = DB::table('habilitaciones as habilitacion')
            ->leftJoin('users as autor', 'autor.id', '=', 'habilitacion.registrada_por')
            ->where('habilitacion.examen_id', $examenId)
            ->where('habilitacion.estudiante_id', $estudianteId)
            ->where('habilitacion.por_foto', false)
            ->select([
                'habilitacion.habilitado',
                'habilitacion.aula_id',
                'habilitacion.motivo',
                'habilitacion.updated_at',
                'autor.name as autor',
            ])
            ->first();

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);

        return new CondicionData(
            habilitado: $this->booleano($columnas['habilitado'] ?? null),
            aulaId: $this->enteroONulo($columnas['aula_id'] ?? null),
            motivo: $this->texto($columnas['motivo'] ?? null),
            registradaPor: $this->texto($columnas['autor'] ?? null),
            fecha: $this->texto($columnas['updated_at'] ?? null),
        );
    }

    /**
     * Si esta inscrito (sin baja) en un grupo del examen y no tiene foto.
     */
    private function sinFoto(int $examenId, int $estudianteId): bool
    {
        return DB::table('examen_grupo as incluido')
            ->join('inscripciones as inscripcion', 'inscripcion.grupo_id', '=', 'incluido.grupo_id')
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'inscripcion.estudiante_id')
            ->where('incluido.examen_id', $examenId)
            ->where('inscripcion.estudiante_id', $estudianteId)
            ->whereNull('inscripcion.baja_en')
            ->where(function (Builder $vacia): void {
                $vacia->whereNull('estudiante.foto')->orWhere('estudiante.foto', '');
            })
            ->exists();
    }
}
