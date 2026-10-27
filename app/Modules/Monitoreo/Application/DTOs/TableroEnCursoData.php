<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * El examen en curso visto desde arriba: el examen, si el ingreso esta
 * abierto, sus cifras, sus aulas y sus incidencias.
 */
final readonly class TableroEnCursoData
{
    /**
     * @param  list<AulaEnCursoData>  $aulas
     * @param  list<IncidenciaEnCursoData>  $incidencias
     */
    public function __construct(
        public ExamenEnCursoData $examen,
        public string $horaServidor,
        public AvanceEnCursoData $avance,
        public array $aulas,
        public array $incidencias,
        public bool $esDocente = false,
    ) {}

    public function incidenciasSinAtender(): int
    {
        return count(array_filter(
            $this->incidencias,
            static fn (IncidenciaEnCursoData $incidencia): bool => ! $incidencia->atendida(),
        ));
    }
}
