<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * El examen completo, como lo abre el asistente para editarlo.
 */
final readonly class ExamenDetalleData
{
    /**
     * @param  list<GrupoDeExamenData>  $grupos
     * @param  list<AulaDetalleData>  $aulas
     * @param  list<NormaMarcadaData>  $normasMarcadas
     */
    public function __construct(
        public ExamenResumenData $resumen,
        public ?string $normas,
        public array $grupos,
        public array $aulas,
        public array $normasMarcadas = [],
    ) {}
}
