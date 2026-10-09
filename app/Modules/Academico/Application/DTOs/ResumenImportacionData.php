<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\DTOs;

/**
 * Como termino la importacion de la oferta de una facultad.
 */
final readonly class ResumenImportacionData
{
    /**
     * @param  string|null  $periodoCodigo  el de `INFO.SEMESTER`
     * @param  string|null  $fechaFuente  `AAAA-MM-DD`, de `INFO.DATE`
     */
    public function __construct(
        public string $facultad,
        public string $sigla,
        public bool $importada,
        public ?string $error = null,
        public ?string $periodoCodigo = null,
        public ?string $fechaFuente = null,
        public int $carreras = 0,
        public int $asignaturas = 0,
        public int $grupos = 0,
        public int $gruposSinDocente = 0,
        public int $docentes = 0,
        public int $aulas = 0,
        public int $sesionesDescartadas = 0,
        public int $carrerasSinCodigo = 0,
        public int $gruposEliminados = 0,
        public ?int $importacionId = null,
    ) {}

    /**
     * El resumen que se guarda en `importaciones_oferta.resumen`.
     *
     * @return array<string, int>
     */
    public function resumen(): array
    {
        return [
            'carreras' => $this->carreras,
            'asignaturas' => $this->asignaturas,
            'grupos' => $this->grupos,
            'grupos_sin_docente' => $this->gruposSinDocente,
            'docentes' => $this->docentes,
            'aulas' => $this->aulas,
            'sesiones_descartadas' => $this->sesionesDescartadas,
            'carreras_sin_codigo' => $this->carrerasSinCodigo,
            'grupos_eliminados' => $this->gruposEliminados,
        ];
    }
}
