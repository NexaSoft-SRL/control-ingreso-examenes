<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class ConsultarBitacoraData
{
    public function __construct(
        public ?int $usuarioId,
        public ?string $fecha,
        public ?string $operacion,
        // El backlog pide filtrar por rango de fechas; "fecha" se mantiene
        // para quien ya consultaba por un solo dia.
        public ?string $desde = null,
        public ?string $hasta = null,
    ) {}
}
