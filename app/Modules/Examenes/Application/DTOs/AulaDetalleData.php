<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Un aula del examen con su ubicacion.
 */
final readonly class AulaDetalleData
{
    /**
     * @param  array{id: int, nombre: string, rol?: string}|null  $aCargo
     * @param  list<array{id: int, nombre: string, rol?: string}>  $apoyo
     */
    public function __construct(
        public int $aulaId,
        public string $nombre,
        public ?string $ubicacion,
        public ?int $edificioId,
        public ?array $aCargo = null,
        public array $apoyo = [],
        public ?string $codigo = null,
    ) {}
}
