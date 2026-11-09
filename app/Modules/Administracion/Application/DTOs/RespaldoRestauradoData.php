<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class RespaldoRestauradoData
{
    public function __construct(
        public int $tablas,
        // Instante en que se genero el respaldo restaurado.
        public string $generadoEn,
    ) {}
}
