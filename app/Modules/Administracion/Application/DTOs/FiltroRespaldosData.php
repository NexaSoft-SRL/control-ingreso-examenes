<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class FiltroRespaldosData
{
    public function __construct(
        public ?string $filtro = null,
        public int $pagina = 1,
        public int $porPagina = 15,
    ) {}
}
