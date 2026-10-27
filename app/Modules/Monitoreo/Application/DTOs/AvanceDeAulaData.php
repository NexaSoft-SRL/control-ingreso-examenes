<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * Asignados son los habilitados repartidos en el aula; faltan, los de
 * ellos que aun no ingresaron. Ingresados cuenta lo registrado por la
 * puerta del aula, que puede incluir a quien entro por otra aula: `deOtra`
 * son los que entraron por esta siendo de otra; `enOtra`, los de esta que
 * entraron por otra.
 */
final readonly class AvanceDeAulaData
{
    public function __construct(
        public int $asignados,
        public int $ingresados,
        public int $faltan,
        public ?string $ultimoRegistro,
        public int $deOtra = 0,
        public int $enOtra = 0,
    ) {}
}
