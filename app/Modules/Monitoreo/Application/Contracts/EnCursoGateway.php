<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\Contracts;

use App\Modules\Monitoreo\Application\DTOs\AvanceEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\IncidenciaEnCursoData;

/**
 * Lecturas del examen en curso que no expone otro modulo: el avance del
 * ingreso por aula y las incidencias reportadas por personas. Solo lee.
 */
interface EnCursoGateway
{
    /**
     * Ingresados, asignados y faltantes del examen y de cada aula, con la
     * hora del ultimo ingreso.
     */
    public function avance(int $examenId): AvanceEnCursoData;

    /**
     * Las incidencias reportadas en las aulas del examen, de la mas reciente
     * a la mas antigua. Los rechazos que la puerta registra sola
     * (`incidencias_ingreso`) no entran.
     *
     * @return list<IncidenciaEnCursoData>
     */
    public function incidencias(int $examenId): array;

    public function incidencia(int $incidenciaId): ?IncidenciaEnCursoData;
}
