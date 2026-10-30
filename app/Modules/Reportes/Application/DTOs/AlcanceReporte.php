<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Sobre que examenes se arma un reporte: los que dejan los filtros o, si
 * se eligio uno, solo ese examen (los demas filtros dejan de contar).
 */
final readonly class AlcanceReporte
{
    /**
     * @param  ?string  $facultad  clave de la facultad (`fcyt`)
     */
    public function __construct(
        public ?int $periodoId = null,
        public ?string $facultad = null,
        public ?string $buscar = null,
        public ?string $desde = null,
        public ?string $hasta = null,
        public ?int $examenId = null,
    ) {}

    public static function deExamen(int $examenId): self
    {
        return new self(examenId: $examenId);
    }

    /**
     * Los mismos filtros sin el examen elegido: es el alcance del listado,
     * que sigue mostrando los demas para poder elegir otro.
     */
    public function sinExamen(): self
    {
        return new self(
            $this->periodoId,
            $this->facultad,
            $this->buscar,
            $this->desde,
            $this->hasta,
        );
    }

    public function sinFacultad(): self
    {
        return new self(
            $this->periodoId,
            null,
            $this->buscar,
            $this->desde,
            $this->hasta,
            $this->examenId,
        );
    }
}
