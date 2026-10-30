<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Contracts;

use App\Modules\Reportes\Application\DTOs\ArchivoReporte;

interface GeneradorArchivoBitacora
{
    /**
     * @param  array{id: int, tipo: string, asignatura: string, fecha: string, hora: string}  $examen
     * @param  list<array{
     *     id: string,
     *     ocurrida_en: string,
     *     categoria: string,
     *     suceso: string,
     *     estudiante: string,
     *     codigo: string|null,
     *     aula_id: int|null,
     *     aula: string|null,
     *     detalle: string,
     *     actor: string
     * }>  $eventos
     */
    public function generar(array $examen, array $eventos, string $formato): ArchivoReporte;
}
