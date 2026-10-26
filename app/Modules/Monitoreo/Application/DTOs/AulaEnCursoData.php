<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * Un aula del examen en curso: quien la atiende (HU-18), su avance y sus
 * senales. `compartida` lista los otros examenes que la usan a la misma
 * hora («Asignatura · Tipo»). `puedeOperar` dice si la cuenta opera su
 * puerta, con la misma regla que la puerta (HU-19).
 */
final readonly class AulaEnCursoData
{
    /**
     * @param  array{id: int, nombre: string, rol?: string}|null  $aCargo
     * @param  list<array{id: int, nombre: string, rol?: string}>  $apoyo
     * @param  list<string>  $compartida
     */
    public function __construct(
        public int $aulaId,
        public string $nombre,
        public ?string $ubicacion,
        public ?int $edificioId,
        public ?array $aCargo,
        public array $apoyo,
        public AvanceDeAulaData $avance,
        public int $incidenciasSinAtender,
        public array $compartida,
        public bool $codigoAcceso,
        public bool $aMiCargo,
        public bool $puedeOperar,
    ) {}
}
