<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Academico\Application\Contracts\PeriodoGateway;
use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\Contracts\PlantillaNormaGateway;
use App\Modules\Examenes\Application\DTOs\ExamenActualData;
use App\Modules\Examenes\Application\DTOs\GrupoDeExamenData;
use App\Modules\Examenes\Application\DTOs\GuardarExamenData;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;
use App\Modules\Examenes\Domain\Exceptions\GrupoYaTieneExamenException;
use App\Modules\Examenes\Domain\Rules\AvanceDeExamen;

/**
 * Las reglas del examen que dependen de lo ya registrado: la asignatura y
 * sus grupos, el periodo, el tipo por grupo y las normas que se pueden
 * marcar. No comprueba el solape de aulas: el aula compartida es un aviso.
 */
final readonly class ComprobarDatosDeExamen
{
    public function __construct(
        private ConsultaExamenGateway $consulta,
        private PeriodoGateway $periodos,
        private PlantillaNormaGateway $plantillas,
    ) {}

    /**
     * Devuelve los datos listos para guardar, con el periodo resuelto. Con
     * `$soloNormas` (examen con ingresos) lo demas no cambia y no se vuelve
     * a comprobar.
     *
     * @throws DatoInvalidoException
     */
    public function execute(
        GuardarExamenData $datos,
        int $usuarioId,
        ?ExamenActualData $actual = null,
        bool $soloNormas = false,
    ): GuardarExamenData {
        $periodoId = $actual !== null && $soloNormas
            ? $actual->periodoId
            : $this->periodoDeLosGrupos($datos, $usuarioId, $actual);

        $this->comprobarNormasMarcadas($datos->normasMarcadas, $usuarioId);

        return new GuardarExamenData(
            asignaturaId: $datos->asignaturaId,
            tipo: $datos->tipo,
            fecha: $datos->fecha,
            horaInicio: $datos->horaInicio,
            duracionMinutos: $datos->duracionMinutos,
            normas: $datos->normas,
            grupos: $datos->grupos,
            aulas: $datos->aulas,
            normasMarcadas: $datos->normasMarcadas,
            normasConservadas: $datos->normasConservadas,
            periodoId: $periodoId,
        );
    }

    /**
     * Cada norma marcada es una predefinida o una plantilla de quien
     * registra. La de otra cuenta se rechaza igual que la que no existe.
     *
     * @param  list<int>  $plantillaIds
     *
     * @throws DatoInvalidoException
     */
    private function comprobarNormasMarcadas(array $plantillaIds, int $usuarioId): void
    {
        if ($plantillaIds === []) {
            return;
        }

        $visibles = [];

        foreach ($this->plantillas->visiblesPara($usuarioId) as $plantilla) {
            $visibles[$plantilla->id] = true;
        }

        foreach ($plantillaIds as $plantillaId) {
            if (! isset($visibles[$plantillaId])) {
                throw new DatoInvalidoException('normas_marcadas', 'La norma no existe.');
            }
        }
    }

    private function periodoDeLosGrupos(
        GuardarExamenData $datos,
        int $usuarioId,
        ?ExamenActualData $actual,
    ): int {
        $vigentes = [];

        foreach ($this->periodos->vigentes() as $periodo) {
            $vigentes[] = $periodo->id;
        }

        $periodoIds = $vigentes;

        // Al modificar, los grupos del examen siguen valiendo aunque su
        // periodo ya haya cerrado.
        if ($actual !== null && ! in_array($actual->periodoId, $periodoIds, true)) {
            $periodoIds[] = $actual->periodoId;
        }

        $opciones = [];
        $tieneGrupoPropio = false;

        foreach ($this->consulta->gruposDeAsignatura($datos->asignaturaId, $periodoIds, $usuarioId) as $grupo) {
            $opciones[$grupo->id] = $grupo;

            if ($grupo->propio && in_array($grupo->periodoId, $vigentes, true)) {
                $tieneGrupoPropio = true;
            }
        }

        $mismaAsignatura = $actual !== null && $actual->asignaturaId === $datos->asignaturaId;

        if (! $tieneGrupoPropio && ! $mismaAsignatura) {
            throw new DatoInvalidoException(
                'asignatura_id',
                'No tienes grupos de esta asignatura en un período vigente.',
            );
        }

        $elegidos = [];

        foreach ($datos->grupos as $grupoId) {
            if (! isset($opciones[$grupoId])) {
                throw new DatoInvalidoException(
                    'grupos',
                    'Los grupos deben ser de la asignatura del examen y de un período vigente.',
                );
            }

            $elegidos[] = $opciones[$grupoId];
        }

        $primero = $elegidos[0] ?? null;

        if (! $primero instanceof GrupoDeExamenData) {
            throw new DatoInvalidoException('grupos', 'Elige al menos un grupo.');
        }

        foreach ($elegidos as $grupo) {
            if ($grupo->periodoId !== $primero->periodoId) {
                throw new DatoInvalidoException('grupos', 'Los grupos deben ser del mismo período.');
            }
        }

        if (
            ($primero->periodoInicio !== null && $datos->fecha < $primero->periodoInicio)
            || ($primero->periodoFin !== null && $datos->fecha > $primero->periodoFin)
        ) {
            throw new DatoInvalidoException(
                'fecha',
                "La fecha debe estar dentro del período {$primero->periodoCodigo}.",
            );
        }

        $ocupados = $this->consulta->gruposConExamenDeTipo($datos->grupos, $datos->tipo, $actual?->id);

        if ($ocupados !== []) {
            throw new GrupoYaTieneExamenException(
                $ocupados[0],
                AvanceDeExamen::tipoConArticulo(TipoExamen::from($datos->tipo)),
            );
        }

        return $primero->periodoId;
    }
}
