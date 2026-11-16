<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * Lo que un puesto decidio sin conexion: una lectura y su resultado, con la
 * hora en que ocurrio segun el puesto ya corregida.
 */
final readonly class EventoSinConexionData
{
    public function __construct(
        public string $uid,
        public string $hora,
        public string $resultado,
        public ?string $medio = null,
        public ?int $examenId = null,
        public ?int $estudianteId = null,
        public ?string $token = null,
        public ?string $detalle = null,
    ) {}
}
