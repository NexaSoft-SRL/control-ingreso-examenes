<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Application\DTOs\IngresoData;
use App\Modules\Ingreso\Application\DTOs\NuevoIngresoData;

/**
 * El ingreso es unico por estudiante y examen, y no se modifica ni se
 * borra: aqui solo se lee y se inserta.
 */
interface IngresoGateway
{
    public function deEstudiante(int $examenId, int $estudianteId): ?IngresoData;

    public function porId(int $ingresoId): ?IngresoData;

    /**
     * Asienta el ingreso y su fila de bitacora. Devuelve nulo si otro
     * puesto lo registro antes (lo impide el unico de la tabla).
     */
    public function registrar(NuevoIngresoData $datos): ?IngresoData;
}
