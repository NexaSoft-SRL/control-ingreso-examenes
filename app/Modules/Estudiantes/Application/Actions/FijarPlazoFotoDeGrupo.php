<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Estudiantes\Application\Contracts\InscripcionGateway;
use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;
use App\Modules\Estudiantes\Application\DTOs\PlazoFotoData;
use App\Modules\Estudiantes\Application\DTOs\PlazoFotoDeGrupoData;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;

/**
 * El docente fija hasta que dia los inscritos de su grupo suben su foto,
 * lo mueve o lo quita. Se guarda en el mismo plazo de grupo que fija la
 * administracion (`plazos_foto_grupo`). Vencido, quien no la subio queda
 * inhabilitado en los examenes del grupo hasta que la suba.
 */
final readonly class FijarPlazoFotoDeGrupo
{
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public function __construct(
        private InscripcionGateway $inscripciones,
        private PortalEstudianteGateway $portal,
        private AlcanceDocenteGateway $alcance,
        private BitacoraGateway $bitacora,
    ) {}

    /**
     * Nulo si el grupo no existe. Sin `$hasta`, quita el plazo.
     *
     * @throws GrupoAjenoException
     */
    public function execute(int $usuarioId, int $grupoId, ?string $hasta, string $hoy): ?PlazoFotoDeGrupoData
    {
        $grupo = $this->inscripciones->grupo($grupoId);

        if ($grupo === null) {
            return null;
        }

        if (! $this->alcance->esGrupoDelDocente($usuarioId, $grupoId)) {
            throw new GrupoAjenoException;
        }

        $antes = $this->inscripciones->plazoFotoDeGrupo($grupoId);

        if ($antes->hasta !== $hasta) {
            if ($hasta === null) {
                $this->portal->quitarPlazoDeGrupo($grupoId);
            } else {
                // Abierto desde hoy, o desde antes si ya lo estaba.
                $desde = $antes->desde !== null && $antes->desde < $hoy ? $antes->desde : $hoy;
                $this->portal->fijarPlazoDeGrupo($grupoId, new PlazoFotoData($desde, $hasta), $usuarioId);
            }

            $this->bitacora->registrar(
                $usuarioId,
                'grupo.plazo_foto',
                'grupos',
                $grupoId,
                sprintf(
                    'Plazo de fotos del grupo %s %s: %s → %s.',
                    $grupo->asignaturaCodigo,
                    $grupo->codigo,
                    $this->dia($antes->hasta),
                    $this->dia($hasta),
                ),
            );
        }

        return $this->inscripciones->plazoFotoDeGrupo($grupoId);
    }

    // '2026-11-15' → '15 nov 2026'; sin plazo, «—».
    private function dia(?string $fecha): string
    {
        if ($fecha === null) {
            return '—';
        }

        [$anio, $mes, $dia] = array_map(intval(...), explode('-', $fecha) + [0, 0, 0]);

        return sprintf('%d %s %d', $dia, self::MESES[$mes - 1] ?? '', $anio);
    }
}
