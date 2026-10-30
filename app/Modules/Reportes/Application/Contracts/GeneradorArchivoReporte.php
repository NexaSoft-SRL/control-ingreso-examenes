<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Contracts;

use App\Modules\Reportes\Application\DTOs\ArchivoReporte;

interface GeneradorArchivoReporte
{
    /**
     * @param  array{
     *     fecha: string,
     *     hora: string,
     *     periodo: string,
     *     tipo: string,
     *     asignatura: array{nombre: string},
     *     filas: array{
     *         asistencia: list<array{codigo: string, estudiante: string, grupo: string, aula: string, hora: string|null, medio: string, situacion: string}>,
     *         ausentes: list<array{apellidos: string, nombres: string, codigo: string, grupo: string, carrera: string, aula: string}>,
     *         incidencias: list<array{hora: string, aula: string, codigo: string|null, estudiante: string, tipo: string, detalle: string, registrado_por: string}>
     *     }
     * }  $reporte
     */
    public function generar(array $reporte, string $tipo, string $formato): ArchivoReporte;
}
