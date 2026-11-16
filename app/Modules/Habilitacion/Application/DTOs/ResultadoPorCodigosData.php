<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

/**
 * Lo que dejo habilitar o inhabilitar desde una lista de codigos: los que
 * se encontraron entre los inscritos del examen y los que no.
 */
final readonly class ResultadoPorCodigosData
{
    /**
     * @param  list<string>  $noEncontrados
     */
    public function __construct(
        public ResultadoCambioData $cambio,
        public int $encontrados,
        public array $noEncontrados,
    ) {}
}
