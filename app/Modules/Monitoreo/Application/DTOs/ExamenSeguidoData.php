<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * El examen que se sigue, con su ventana medida contra la hora del servidor.
 * `fin` es la hora prevista de termino ('HH:MM'); `excedido`, que esa hora
 * ya paso y el docente todavia no lo finalizo.
 */
final readonly class ExamenSeguidoData
{
    public function __construct(
        public int $id,
        public string $asignatura,
        public string $tipo,
        public string $fecha,
        public string $hora,
        public bool $abierto,
        public bool $esHoy,
        public string $horaServidor,
        public string $fin = '',
        public bool $excedido = false,
    ) {}
}
