<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * La credencial de un estudiante habilitado para un examen. `token` es
 * nulo mientras el codigo no se haya emitido. `correo` es el institucional
 * del estudiante, que se deduce de su codigo universitario.
 */
final readonly class CredencialData
{
    public function __construct(
        public int $examenId,
        public int $estudianteId,
        public ?string $facultad,
        public string $examen,
        public string $fecha,
        public string $hora,
        public int $duracion,
        public string $estudiante,
        public string $codigo,
        public ?AulaData $aula,
        public ?string $token,
        public ?string $correo = null,
    ) {}

    /**
     * El nombre del PNG del codigo: `qr-<codigo-sis>-<fecha>.png`.
     */
    public function nombreDelQr(): string
    {
        return sprintf('qr-%s-%s.png', $this->codigo, $this->fecha);
    }
}
