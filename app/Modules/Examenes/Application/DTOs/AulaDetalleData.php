<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Un aula del examen con su ubicacion y quien la atiende.
 */
final readonly class AulaDetalleData
{
    /**
     * @param  list<PersonaData>  $apoyo
     */
    public function __construct(
        public int $aulaId,
        public string $nombre,
        public ?string $ubicacion,
        public ?int $edificioId,
        public PersonaData $encargado,
        public array $apoyo,
        public bool $usaCodigo,
    ) {}
}
