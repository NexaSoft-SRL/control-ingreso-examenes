<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaAulasGateway;

/**
 * Todas las aulas, ubicadas o no, en orden natural.
 */
final readonly class ListarAulas
{
    public function __construct(
        private ConsultaAulasGateway $aulas,
    ) {}

    /**
     * @return list<array{id: int, nombre: string, edificio_id: int|null, edificio: string|null, piso: string|null, facultad: string|null}>
     */
    public function execute(?string $claveFacultad, bool $soloUbicadas): array
    {
        return $this->aulas->aulas($claveFacultad, $soloUbicadas);
    }
}
