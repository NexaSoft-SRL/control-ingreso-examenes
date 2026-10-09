<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\DTOs;

/**
 * El archivo de oferta de una facultad, ya leido.
 */
final readonly class OfertaFacultadData
{
    /**
     * @param  string|null  $periodoCodigo  `2/2026`, de `INFO.SEMESTER`
     * @param  string|null  $fechaFuente  `AAAA-MM-DD`, de `INFO.DATE`
     * @param  list<CarreraOfertaData>  $carreras
     */
    public function __construct(
        public string $facultad,
        public ?string $periodoCodigo,
        public ?string $fechaFuente,
        public array $carreras,
    ) {}

    public function tieneCarrerasAnuales(): bool
    {
        foreach ($this->carreras as $carrera) {
            if ($carrera->anual) {
                return true;
            }
        }

        return false;
    }

    public function tieneCarrerasSemestrales(): bool
    {
        foreach ($this->carreras as $carrera) {
            if (! $carrera->anual) {
                return true;
            }
        }

        return false;
    }
}
