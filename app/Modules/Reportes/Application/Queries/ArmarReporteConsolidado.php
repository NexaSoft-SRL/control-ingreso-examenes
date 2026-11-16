<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Queries;

use App\Modules\Reportes\Application\DTOs\ReporteConsolidadoData;
use App\Modules\Reportes\Application\DTOs\TablaReporte;
use App\Modules\Reportes\Domain\Exceptions\ExamenSinRendirException;
use App\Modules\Reportes\Domain\Exceptions\RecursoAjenoException;

final readonly class ArmarReporteConsolidado
{
    public function __construct(
        private CompararReportesDocente $comparar,
        private ArmarTabla $armarTabla,
    ) {}

    /**
     * @param  list<int>  $examenIds
     *
     * @throws RecursoAjenoException
     * @throws ExamenSinRendirException
     */
    public function execute(array $examenIds, int $usuarioId): ReporteConsolidadoData
    {
        $comparacion = $this->comparar->execute($usuarioId, $examenIds);

        if (count($comparacion['examenes']) !== count($examenIds)) {
            throw new ExamenSinRendirException('Todos los exámenes deben haberse rendido para consolidar sus reportes.');
        }

        $filasResumen = [];
        $hojas = [];

        foreach ([
            'asistencia' => 'Asistencia',
            'ausentes' => 'Ausentes',
            'incidencias' => 'Incidencias',
        ] as $clave => $titulo) {
            $encabezados = null;
            $filas = [];

            foreach ($examenIds as $examenId) {
                $tabla = $this->armarTabla->deExamen($clave, $examenId, $usuarioId);

                if ($tabla === null) {
                    throw new RecursoAjenoException('Uno o más exámenes no están a su cargo.');
                }

                $encabezados ??= ['Examen', ...$tabla->encabezados];
                $nombreExamen = $tabla->filtros[0] ?? (string) $examenId;

                foreach ($tabla->filas as $fila) {
                    $filas[] = [$nombreExamen, ...$fila];
                }
            }

            $hojas[] = new TablaReporte(
                clave: 'consolidado_'.$clave,
                titulo: $titulo,
                filtros: [count($examenIds).' exámenes seleccionados.'],
                encabezados: $encabezados ?? ['Examen'],
                filas: $filas,
                totales: [count($filas).' registros.'],
                emitidoEn: now()->format('Y-m-d H:i:s'),
            );
        }

        foreach ($comparacion['examenes'] as $examen) {
            $filasResumen[] = [
                $examen['periodo'],
                $examen['asignatura'],
                $examen['tipo'],
                $examen['fecha'],
                (string) $examen['presentes'],
                (string) $examen['habilitados'],
                (string) $examen['ausentes'],
                $examen['asistencia'].' %',
                (string) $examen['incidencias'],
            ];
        }

        $resumen = new TablaReporte(
            clave: 'reportes_consolidados_'.now()->format('Y-m-d'),
            titulo: 'Resumen consolidado de exámenes',
            filtros: [count($examenIds).' exámenes seleccionados.'],
            encabezados: [
                'Período',
                'Asignatura',
                'Tipo',
                'Fecha',
                'Ingresaron',
                'Habilitados',
                'Ausentes',
                'Asistencia',
                'Incidencias',
            ],
            filas: $filasResumen,
            totales: [count($filasResumen).' exámenes.'],
            emitidoEn: now()->format('Y-m-d H:i:s'),
        );

        return new ReporteConsolidadoData(
            resumen: $resumen,
            hojas: [$resumen, ...$hojas],
        );
    }
}
