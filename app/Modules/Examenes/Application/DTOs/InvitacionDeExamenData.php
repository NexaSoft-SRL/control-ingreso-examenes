<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Un grupo de otro docente al que se invito a sumarse al examen y que aun
 * no esta en el: `pendiente` (no respondio) o `rechazada`.
 */
final readonly class InvitacionDeExamenData
{
    public const PENDIENTE = 'pendiente';

    public const RECHAZADA = 'rechazada';

    public function __construct(
        public int $id,
        public int $grupoId,
        public string $grupoCodigo,
        public ?string $docente,
        public string $estado,
    ) {}
}
