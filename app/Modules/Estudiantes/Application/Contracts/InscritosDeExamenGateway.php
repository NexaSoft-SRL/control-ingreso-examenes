<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Contracts;

use App\Modules\Estudiantes\Application\DTOs\EstudianteData;

/**
 * Contrato publico para los demas modulos: quienes rinden un examen son
 * los inscritos de sus grupos, no el padron entero.
 */
interface InscritosDeExamenGateway
{
    /**
     * Los estudiantes activos inscritos en alguno de esos grupos, una vez
     * cada uno aunque esten en varios, por apellidos y nombres.
     *
     * @param  array<int>  $grupoIds
     * @return list<EstudianteData>
     */
    public function estudiantesDe(array $grupoIds): array;
}
