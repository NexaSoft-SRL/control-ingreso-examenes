<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Queries;

use App\Modules\Estudiantes\Application\Actions\CargarInscritos;
use App\Modules\Estudiantes\Application\Contracts\GeneradorPlantilla;
use App\Modules\Estudiantes\Domain\Enums\AlcanceCarga;

/**
 * La plantilla es la fila de encabezados que espera la carga: la de un
 * grupo o la de una facultad.
 */
final readonly class ArmarPlantilla
{
    public const NOMBRE = 'plantilla_inscritos.xlsx';

    public function __construct(
        private GeneradorPlantilla $generador,
    ) {}

    /**
     * @return string el contenido del `.xlsx`
     */
    public function execute(AlcanceCarga $alcance): string
    {
        return $this->generador->generar(
            $alcance === AlcanceCarga::Facultad
                ? CargarInscritos::COLUMNAS_FACULTAD
                : CargarInscritos::COLUMNAS_GRUPO,
        );
    }
}
