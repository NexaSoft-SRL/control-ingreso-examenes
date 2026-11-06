<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Un examen en el listado del docente, con sus cifras y su avance.
 * `rendido`: lo finalizo un docente o ya paso su dia. `atienden`: personas
 * distintas a cargo o de apoyo en sus aulas. `codigosAula`: aulas con codigo
 * de acceso.
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
        public ?string $finalizadoEn = null,
        public bool $conIngresos = false,
        public bool $conIncidencias = false,
        public int $codigosAula = 0,
        public int $atienden = 0,
    ) {}
}
