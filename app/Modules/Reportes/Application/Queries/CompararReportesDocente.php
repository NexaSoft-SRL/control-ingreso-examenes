<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Queries;

use App\Modules\Reportes\Application\Contracts\ReporteGateway;
use App\Modules\Reportes\Application\DTOs\FilaComparacion;
use App\Modules\Reportes\Domain\Exceptions\RecursoAjenoException;

final readonly class CompararReportesDocente
{
    public function __construct(
        private ReporteGateway $reportes,
    ) {}

    /**
     * @param  list<int>  $examenIds
     * @param  list<int>  $periodoIds
     * @return array{
     *     examenes: list<array{
     *         id: int,
     *         periodo_id: int,
     *         periodo: string,
     *         asignatura: string,
     *         tipo: string,
     *         fecha: string,
     *         presentes: int,
     *         habilitados: int,
     *         ausentes: int,
     *         incidencias: int,
     *         asistencia: int,
     *     }>,
     *     periodos: list<array{
     *         id: int,
     *         codigo: string,
     *         examenes: int,
     *         presentes: int,
     *         habilitados: int,
     *         ausentes: int,
     *         incidencias: int,
     *         asistencia: int,
     *     }>,
     * }
     */
    public function execute(int $usuarioId, array $examenIds = [], array $periodoIds = []): array
    {
        $filas = $this->reportes->comparablesDelDocente($usuarioId);
        $porId = [];
        $porPeriodo = [];

        foreach ($filas as $fila) {
            $porId[$fila->id] = $fila;
            $porPeriodo[$fila->periodoId] ??= [
                'id' => $fila->periodoId,
                'codigo' => $fila->periodo,
                'examenes' => 0,
                'presentes' => 0,
                'habilitados' => 0,
                'ausentes' => 0,
                'incidencias' => 0,
            ];
        }

        foreach ($examenIds as $examenId) {
            if (! isset($porId[$examenId])) {
                throw new RecursoAjenoException('Uno o más exámenes no están a su cargo.');
            }
        }

        foreach ($periodoIds as $periodoId) {
            if (! isset($porPeriodo[$periodoId])) {
                throw new RecursoAjenoException('Uno o más períodos no tienen exámenes a su cargo.');
            }
        }

        $examenes = [];

        foreach ($examenIds as $examenId) {
            $fila = $porId[$examenId];

            if ($fila->rendido) {
                $examenes[] = $this->datosDeExamen($fila);
            }
        }

        foreach ($filas as $fila) {
            if (! $fila->rendido || ! in_array($fila->periodoId, $periodoIds, true)) {
                continue;
            }

            $porPeriodo[$fila->periodoId] = $this->acumularFilaPeriodo(
                $porPeriodo[$fila->periodoId],
                $fila,
            );
        }

        $periodos = [];

        foreach ($periodoIds as $periodoId) {
            $periodo = $porPeriodo[$periodoId];
            $periodo['asistencia'] = $this->porcentajeAsistencia(
                $periodo['presentes'],
                $periodo['habilitados'],
            );
            $periodos[] = $periodo;
        }

        return [
            'examenes' => $examenes,
            'periodos' => $periodos,
        ];
    }

    /**
     * @return array{
     *     examenes: list<array{
     *         id: int,
     *         periodo_id: int,
     *         periodo: string,
     *         asignatura: string,
     *         tipo: string,
     *         fecha: string,
     *         rendido: bool,
     *         presentes: int|null,
     *         habilitados: int,
     *         ausentes: int|null,
     *         incidencias: int|null,
     *         asistencia: int|null,
     *     }>,
     *     periodos: list<array{id: int, codigo: string, examenes: int}>,
     * }
     */
    public function opciones(int $usuarioId): array
    {
        $filas = $this->reportes->comparablesDelDocente($usuarioId);
        $examenes = [];
        $periodos = [];

        foreach ($filas as $fila) {
            $periodos[$fila->periodoId] ??= [
                'id' => $fila->periodoId,
                'codigo' => $fila->periodo,
                'examenes' => 0,
            ];
            $periodos[$fila->periodoId]['examenes']++;
            $examenes[] = [
                'id' => $fila->id,
                'periodo_id' => $fila->periodoId,
                'periodo' => $fila->periodo,
                'asignatura' => $fila->asignatura,
                'tipo' => $fila->tipo,
                'fecha' => $fila->fecha,
                'rendido' => $fila->rendido,
                'presentes' => $fila->rendido ? $fila->presentes : null,
                'habilitados' => $fila->habilitados,
                'ausentes' => $fila->rendido ? $fila->ausentes : null,
                'incidencias' => $fila->rendido ? $fila->incidencias : null,
                'asistencia' => $fila->rendido ? $fila->asistencia() : null,
            ];
        }

        return [
            'examenes' => $examenes,
            'periodos' => array_values($periodos),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     periodo_id: int,
     *     periodo: string,
     *     asignatura: string,
     *     tipo: string,
     *     fecha: string,
     *     presentes: int,
     *     habilitados: int,
     *     ausentes: int,
     *     incidencias: int,
     *     asistencia: int,
     * }
     */
    private function datosDeExamen(FilaComparacion $fila): array
    {
        return [
            'id' => $fila->id,
            'periodo_id' => $fila->periodoId,
            'periodo' => $fila->periodo,
            'asignatura' => $fila->asignatura,
            'tipo' => $fila->tipo,
            'fecha' => $fila->fecha,
            'presentes' => $fila->presentes,
            'habilitados' => $fila->habilitados,
            'ausentes' => $fila->ausentes,
            'incidencias' => $fila->incidencias,
            'asistencia' => $fila->asistencia(),
        ];
    }

    /**
     * @param  array{id: int, codigo: string, examenes: int, presentes: int, habilitados: int, ausentes: int, incidencias: int}  $periodo
     * @return array{id: int, codigo: string, examenes: int, presentes: int, habilitados: int, ausentes: int, incidencias: int}
     */
    private function acumularFilaPeriodo(array $periodo, FilaComparacion $fila): array
    {
        return [
            ...$periodo,
            'examenes' => $periodo['examenes'] + 1,
            'presentes' => $periodo['presentes'] + $fila->presentes,
            'habilitados' => $periodo['habilitados'] + $fila->habilitados,
            'ausentes' => $periodo['ausentes'] + $fila->ausentes,
            'incidencias' => $periodo['incidencias'] + $fila->incidencias,
        ];
    }

    private function porcentajeAsistencia(int $presentes, int $habilitados): int
    {
        if ($habilitados === 0) {
            return 0;
        }

        return (int) round($presentes * 100 / $habilitados);
    }
}
