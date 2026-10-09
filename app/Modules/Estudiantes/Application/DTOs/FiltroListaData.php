<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * Pagina y filtros de un listado del padron.
 */
final readonly class FiltroListaData
{
    public function __construct(
        public int $pagina = 1,
        public int $porPagina = 25,
        public ?string $buscar = null,
        public ?string $facultad = null,
        public ?int $carreraId = null,
        public ?string $estado = null,
    ) {}
}
