<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Estudiantes\Application\Contracts\InscripcionGateway;
use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;
use App\Modules\Estudiantes\Domain\Exceptions\CambioDeFotoNoPermitidoException;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;
use App\Modules\Estudiantes\Domain\Exceptions\NoInscritoException;
use App\Modules\Estudiantes\Domain\Rules\CambioDeFoto;

/**
 * Un docente habilita a un estudiante de su grupo para que suba o cambie
 * su foto hasta el dia que elige, aunque el plazo general este cerrado. Si
 * ya esta habilitado, cualquier docente de sus grupos cambia ese dia. No la
 * sube el docente: la sube el estudiante desde su acceso.
 */
final readonly class HabilitarCambioDeFoto
{
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public function __construct(
        private InscripcionGateway $inscripciones,
        private AlcanceDocenteGateway $alcance,
        private PortalEstudianteGateway $portal,
        private BitacoraGateway $bitacora,
    ) {}

    /**
     * Hasta que dia queda habilitado, o nulo si el grupo no existe. Sin
     * `$hasta`, una semana desde hoy.
     *
     * @throws GrupoAjenoException
     * @throws NoInscritoException
     * @throws CambioDeFotoNoPermitidoException
     */
    public function execute(int $usuarioId, int $grupoId, int $estudianteId, string $hoy, ?string $hasta = null): ?string
    {
        if ($this->inscripciones->grupo($grupoId) === null) {
            return null;
        }

        if (! $this->alcance->esGrupoDelDocente($usuarioId, $grupoId)) {
            throw new GrupoAjenoException;
        }

        if ($this->inscripciones->existentes([$estudianteId], [$grupoId]) === []) {
            throw new NoInscritoException;
        }

        $estado = $this->portal->estadoFoto($estudianteId);
        $hasta ??= CambioDeFoto::habilitadaHasta($hoy);
        $vigente = $estado['habilitada_hasta'] !== null && $estado['habilitada_hasta'] >= $hoy;
        $estudiante = $this->portal->estudiante($estudianteId);
        $quien = $estudiante === null ? 'un estudiante' : $estudiante->nombre.' · '.$estudiante->codigo;

        // Ya habilitado: se cambia el dia limite.
        if ($vigente) {
            $antes = (string) $estado['habilitada_hasta'];

            if ($antes !== $hasta) {
                $this->portal->habilitarFoto($estudianteId, $hasta, $usuarioId);
                $this->bitacora->registrar(
                    $usuarioId,
                    'estudiante.foto_habilitar',
                    'estudiantes',
                    $estudianteId,
                    sprintf('Foto de %s: plazo del %s → %s.', $quien, $this->dia($antes), $this->dia($hasta)),
                );
            }

            return $hasta;
        }

        $desde = CambioDeFoto::habilitableDesde($estado['subida_en']);

        if ($desde !== null && $desde > $hoy) {
            throw new CambioDeFotoNoPermitidoException(
                sprintf(
                    'Foto del %s: el cambio se puede habilitar desde el %s.',
                    $this->dia(substr((string) $estado['subida_en'], 0, 10)),
                    $this->dia($desde),
                ),
                'FOTO_RECIENTE',
            );
        }

        $this->portal->habilitarFoto($estudianteId, $hasta, $usuarioId);
        $this->bitacora->registrar(
            $usuarioId,
            'estudiante.foto_habilitar',
            'estudiantes',
            $estudianteId,
            sprintf(
                '%s de %s habilitado hasta el %s.',
                $desde === null ? 'Subir la foto' : 'Cambiar la foto',
                $quien,
                $this->dia($hasta),
            ),
        );

        return $hasta;
    }

    // '2026-10-30' → '30 oct 2026'.
    private function dia(string $fecha): string
    {
        [$anio, $mes, $dia] = array_map(intval(...), explode('-', $fecha) + [0, 0, 0]);

        return sprintf('%d %s %d', $dia, self::MESES[$mes - 1] ?? '', $anio);
    }
}
