<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * La habilitacion de un estudiante en un examen, tal como la necesita la
 * puerta. Sin fila, el estudiante esta «sin revisar».
 */
final readonly class CondicionData
{
    public function __construct(
        public bool $habilitado,
        public ?int $aulaId,
        public ?string $motivo,
        public ?string $registradaPor,
        public ?string $fecha,
    ) {}
}
