<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * Lo que la credencial y el aviso dicen del examen, igual para todos sus
 * estudiantes. `texto` es «Primer parcial · Asignatura»; `fechaTexto`,
 * «30 oct 2026»; `facultad`, la sigla de la facultad de su primer grupo.
 */
final readonly class ExamenQrData
{
    /**
     * @param  list<string>  $normasMarcadas
     */
    public function __construct(
        public int $id,
        public string $texto,
        public string $fecha,
        public string $fechaTexto,
        public string $hora,
        public int $duracion,
        public ?string $facultad,
        public array $normasMarcadas = [],
        public ?string $normas = null,
    ) {}
}
