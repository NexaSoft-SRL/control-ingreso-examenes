<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class ConsultarBitacoraData
{
    public function __construct(
        public ?string $buscar,
        public ?string $rol,
        public ?string $operacion,
        // Dias locales `AAAA-MM-DD`, los dos inclusive.
        public ?string $desde,
        public ?string $hasta,
        public int $pagina,
        public int $porPagina,
    ) {}
}
