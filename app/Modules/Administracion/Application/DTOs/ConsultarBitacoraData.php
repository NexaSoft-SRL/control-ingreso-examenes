<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class ConsultarBitacoraData
{
    public function __construct(
        public ?int $usuarioId,
        public ?string $fecha,
        public ?string $operacion,
    ) {}
}
