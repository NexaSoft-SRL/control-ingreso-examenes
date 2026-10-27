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
 * su foto durante unos dias, aunque el plazo general este cerrado. No la
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
     * Hasta que dia queda habilitado, o nulo si el grupo no existe.
     *
     * @throws GrupoAjenoException
     * @throws NoInscritoException
     * @throws CambioDeFotoNoPermitidoException
     */
    public function execute(int $usuarioId, int $grupoId, int $estudianteId, string $hoy): ?string
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

        // Otro docente ya lo habilito: no hace falta de nuevo.
        if ($estado['habilitada_hasta'] !== null && $estado['habilitada_hasta'] >= $hoy) {
            throw new CambioDeFotoNoPermitidoException(
                sprintf(
                    'Ya habilitado hasta el %s por %s.',
                    $this->dia($estado['habilitada_hasta']),
                    $estado['habilitada_por'] ?? 'otro docente',
                ),
                'YA_HABILITADO',
            );
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

        $hasta = CambioDeFoto::habilitadaHasta($hoy);
        $this->portal->habilitarFoto($estudianteId, $hasta, $usuarioId);

        $estudiante = $this->portal->estudiante($estudianteId);
        $this->bitacora->registrar(
            $usuarioId,
            'estudiante.foto_habilitar',
            'estudiantes',
            $estudianteId,
            sprintf(
                '%s de %s habilitado hasta el %s.',
                $desde === null ? 'Subir la foto' : 'Cambiar la foto',
                $estudiante === null ? 'un estudiante' : $estudiante->nombre.' · '.$estudiante->codigo,
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
