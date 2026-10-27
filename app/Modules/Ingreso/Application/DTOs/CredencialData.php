<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * La credencial de un estudiante habilitado para un examen. `contenido` es
 * lo que lleva el QR y es nulo mientras el codigo no se haya emitido.
 * `correo` es el institucional, deducido del codigo universitario. Las
 * normas son las del examen, que el correo del codigo tambien lleva.
 */
final readonly class CredencialData
{
    /**
     * @param  list<string>  $normasMarcadas
     */
    public function __construct(
        public int $examenId,
        public int $estudianteId,
        public ?string $facultad,
        public string $examen,
        public string $fecha,
        public string $fechaTexto,
        public string $hora,
        public int $duracion,
        public string $estudiante,
        public string $codigo,
        public string $correo,
        public ?AulaData $aula,
        public ?string $contenido,
        public ?string $enviadoEn = null,
        public array $normasMarcadas = [],
        public ?string $normas = null,
    ) {}
}
