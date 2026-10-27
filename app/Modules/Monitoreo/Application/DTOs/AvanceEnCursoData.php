<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * El ingreso del examen en cifras. `ultimoRegistro` es el instante del
 * ultimo ingreso (ISO 8601) o nulo si aun no hay.
 */
final readonly class AvanceEnCursoData
{
    /**
     * @param  array<int, AvanceDeAulaData>  $aulas  por id de aula
     * @param  list<array{hora: string, ingresos: int}>  $llegadas  ingresos por tramo de cinco minutos
     */
    public function __construct(
        public int $ingresaron,
        public int $faltan,
        public ?string $ultimoRegistro,
        public array $aulas,
        public array $llegadas = [],
    ) {}

    public function deAula(int $aulaId): AvanceDeAulaData
    {
        return $this->aulas[$aulaId] ?? new AvanceDeAulaData(0, 0, 0, null);
    }
}
