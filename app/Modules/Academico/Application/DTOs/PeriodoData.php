<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\DTOs;

/**
 * Un periodo tal como lo necesitan los demas modulos: fechas en `AAAA-MM-DD`
 * y las ventanas de examenes como vienen de la fuente.
 */
final readonly class PeriodoData
{
    /**
     * @param  array<string, mixed>  $ventanas
     */
    public function __construct(
        public int $id,
        public string $codigo,
        public string $tipo,
        public string $tipoEtiqueta,
        public ?string $fechaInicio,
        public ?string $fechaFin,
        public array $ventanas,
    ) {}
}
