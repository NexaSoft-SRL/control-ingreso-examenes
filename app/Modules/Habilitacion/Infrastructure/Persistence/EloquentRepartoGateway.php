<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Habilitacion\Application\Contracts\RepartoGateway;
use App\Modules\Habilitacion\Application\DTOs\AulaRepartoData;
use App\Modules\Habilitacion\Application\DTOs\RepartoData;
use App\Modules\Habilitacion\Domain\Rules\RepartoEquilibrado;
use Illuminate\Support\Facades\DB;

/**
 * Las aulas del examen y los estudiantes son de otros modulos: se leen por
 * nombre de tabla.
 */
final class EloquentRepartoGateway implements RepartoGateway
{
    use ConvierteColumnas;

    private const LOTE = 500;

    public function __construct(
        private readonly BitacoraGateway $bitacora,
        private readonly RepartoEquilibrado $regla,
    ) {}

    /**
     * @return list<AulaRepartoData>
     */
    public function porAula(int $examenId): array
    {
        $carga = $this->carga($examenId);

        $filas = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->where('asignada.examen_id', $examenId)
            ->orderBy('asignada.id')
            ->select(['aula.id', 'aula.nombre'])
            ->get();

        $aulas = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $aulaId = $this->entero($columnas['id'] ?? null);

            $aulas[] = new AulaRepartoData(
                aulaId: $aulaId,
                nombre: $this->texto($columnas['nombre'] ?? null) ?? '',
                asignados: $carga[$aulaId] ?? 0,
            );
        }

        return $aulas;
    }

    public function repartir(int $examenId, int $usuarioId): RepartoData
    {
        /** @var array{0: int, 1: int} $resultado */
        $resultado = DB::transaction(function () use ($examenId, $usuarioId): array {
            // Bloquear las aulas del examen pone en fila dos repartos
            // simultaneos: el segundo ya ve a los ubicados por el primero.
            $aulaIds = DB::table('examen_aula')
                ->where('examen_id', $examenId)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('aula_id')
                ->all();

            $carga = $this->carga($examenId);
            $cargaPorAula = [];

            foreach ($aulaIds as $aulaId) {
                $aulaId = $this->entero($aulaId);
                $cargaPorAula[$aulaId] = $carga[$aulaId] ?? 0;
            }

            $pendientes = DB::table('habilitaciones as habilitacion')
                ->join('estudiantes as estudiante', 'estudiante.id', '=', 'habilitacion.estudiante_id')
                ->where('habilitacion.examen_id', $examenId)
                ->where('habilitacion.habilitado', true)
                ->whereNull('habilitacion.aula_id')
                ->orderBy('estudiante.apellidos')
                ->orderBy('estudiante.nombres')
                ->orderBy('estudiante.id')
                ->pluck('habilitacion.id')
                ->all();

            $reparto = $this->regla->repartir(
                $cargaPorAula,
                array_values(array_map(fn (mixed $id): int => $this->entero($id), $pendientes)),
            );

            $repartidos = 0;

            foreach ($reparto as $aulaId => $habilitacionIds) {
                foreach (array_chunk($habilitacionIds, self::LOTE) as $lote) {
                    $repartidos += DB::table('habilitaciones')
                        ->whereIn('id', $lote)
                        ->where('habilitado', true)
                        ->whereNull('aula_id')
                        ->update(['aula_id' => $aulaId, 'updated_at' => now()]);
                }
            }

            if ($repartidos > 0) {
                $this->bitacora->registrar(
                    $usuarioId,
                    'habilitacion.repartir',
                    'habilitaciones',
                    $examenId,
                    sprintf(
                        '%d estudiante(s) repartido(s) en %d aula(s) del examen %d.',
                        $repartidos,
                        count($reparto),
                        $examenId,
                    ),
                );
            }

            return [$repartidos, count($reparto)];
        }, 3);

        return new RepartoData(
            repartidos: $resultado[0],
            aulasUsadas: $resultado[1],
            porAula: $this->porAula($examenId),
        );
    }

    /**
     * @return array<int, int> aula => estudiantes asignados
     */
    private function carga(int $examenId): array
    {
        $filas = DB::table('habilitaciones')
            ->where('examen_id', $examenId)
            ->whereNotNull('aula_id')
            ->groupBy('aula_id')
            ->selectRaw('aula_id, count(*) as asignados')
            ->get();

        $carga = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);

            $carga[$this->entero($columnas['aula_id'] ?? null)] = $this->entero($columnas['asignados'] ?? 0);
        }

        return $carga;
    }
}
