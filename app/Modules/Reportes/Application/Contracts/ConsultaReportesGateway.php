<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Contracts;

/**
 * @phpstan-type FilaReporteEstudiante array{
 *     codigo: string,
 *     estudiante: string,
 *     apellidos: string,
 *     nombres: string,
 *     carrera: string,
 *     grupo: string,
 *     habilitado: bool|null,
 *     aula: string,
 *     ingreso_id: int|null,
 *     registrado_en: string|null,
 *     hora: string|null,
 *     medio: string,
 *     excepcion: bool,
 *     expulsado: bool,
 *     situacion: string
 * }
 * @phpstan-type FilaReporteIncidencia array{
 *     hora: string,
 *     aula: string,
 *     codigo: string|null,
 *     estudiante: string,
 *     tipo: string,
 *     gravedad: string,
 *     detalle: string,
 *     registrado_por: string,
 *     atencion: string,
 *     ocurrida_en: string,
 *     rechazado: bool
 * }
 * @phpstan-type ExamenReporte array{
 *     id: int,
 *     periodo: string,
 *     fecha: string,
 *     hora: string,
 *     tipo: string,
 *     asignatura: array{codigo: string, nombre: string},
 *     rendido: bool,
 *     inscritos: int,
 *     ingresaron: int,
 *     habilitados: int,
 *     ausentes: int,
 *     no_habilitados: int,
 *     incidencias: int,
 *     rechazados: int,
 *     asistencia_porcentaje: int,
 *     muestras: array{
 *         asistencia: list<FilaReporteEstudiante>,
 *         ausentes: list<FilaReporteEstudiante>,
 *         incidencias: list<FilaReporteIncidencia>
 *     },
 *     totales: array{asistencia: int, ausentes: int, incidencias: int},
 *     filas: array{
 *         asistencia: list<FilaReporteEstudiante>,
 *         ausentes: list<FilaReporteEstudiante>,
 *         incidencias: list<FilaReporteIncidencia>
 *     }
 * }
 */
interface ConsultaReportesGateway
{
    /**
     * @param  list<int>  $periodoIds
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array{total: int, pagina: int, por_pagina: int, paginas: int, rendidos: int},
     *     asignaturas: list<array{id: int, codigo: string, nombre: string}>
     * }
     */
    public function listar(
        int $usuarioId,
        array $periodoIds,
        ?int $periodoId,
        ?int $asignaturaId,
        int $pagina,
    ): array;

    public function existe(int $examenId): bool;

    public function perteneceAlDocente(int $examenId, int $usuarioId): bool;

    /**
     * @return array{data: list<FilaReporteIncidencia>, meta: array{total: int, pagina: int, por_pagina: int, paginas: int}}
     */
    public function incidencias(int $examenId, int $pagina): array;

    /**
     * @return ExamenReporte|null
     */
    public function reporte(int $examenId): ?array;
}
