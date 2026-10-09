<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class FiltroUsuariosData
{
    public function __construct(
        public ?string $buscar,
        public ?string $rol,
        public bool $soloBloqueadas,
        public int $pagina,
        public int $porPagina,
    ) {}
}
