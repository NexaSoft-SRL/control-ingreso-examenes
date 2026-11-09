<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * Los filtros de la asistencia de un aula. `lista` es `faltan` o `ingresaron`.
 */
final readonly class ConsultaAsistenciaData
{
    public const FALTAN = 'faltan';

    public const INGRESARON = 'ingresaron';

    public function __construct(
        public int $examenId,
        public int $aulaId,
        public string $lista,
        public ?string $buscar,
        public int $pagina,
        public int $porPagina,
    ) {}
}
