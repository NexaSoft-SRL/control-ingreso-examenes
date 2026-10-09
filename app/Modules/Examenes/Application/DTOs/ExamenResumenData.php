<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Un examen en el listado del docente, con sus cifras y su avance.
 */
final readonly class ExamenResumenData
{
    /**
     * @param  list<string>  $grupos
     * @param  list<string>  $aulas
     */
    public function __construct(
        public int $id,
        public int $asignaturaId,
        public string $asignaturaCodigo,
        public string $asignaturaNombre,
        public string $tipo,
        public string $tipoTexto,
        public string $fecha,
        public string $hora,
        public int $duracion,
        public array $grupos,
        public int $inscritos,
        public array $aulas,
        public int $habilitados,
        public int $noHabilitados,
        public int $sinRevisar,
        public int $qrEmitidos,
        public AvanceData $avance,
        public bool $propio,
        public string $registradoPor,
        public int $juntoCon,
        public bool $esHoy,
        public bool $rendido,
    ) {}
}
