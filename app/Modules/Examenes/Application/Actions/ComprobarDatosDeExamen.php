<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Academico\Application\Contracts\PeriodoGateway;
use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\Contracts\PlantillaNormaGateway;
use App\Modules\Examenes\Application\DTOs\AulaDeExamenData;
use App\Modules\Examenes\Application\DTOs\ExamenActualData;
use App\Modules\Examenes\Application\DTOs\GrupoDeExamenData;
use App\Modules\Examenes\Application\DTOs\GuardarExamenData;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;
use App\Modules\Examenes\Domain\Exceptions\GrupoYaTieneExamenException;
use App\Modules\Examenes\Domain\Rules\AvanceDeExamen;

/**
 * Las reglas del examen que dependen de lo ya registrado: la asignatura y
 * sus grupos, el periodo, el tipo por grupo, las normas que se pueden
 * marcar y quien puede atender un aula.
 * No comprueba capacidad ni solape de aulas: el aula compartida es un aviso.
 */
final readonly class ComprobarDatosDeExamen
{
    // Horario en que puede empezar un examen, en minutos del dia.
    private const DESDE = 6 * 60;

    private const HASTA = 22 * 60;

    // La ventana del calendario academico que corresponde a cada tipo.
    private const VENTANA_DE_TIPO = [
        'PRIMER_PARCIAL' => 'primeros_parciales',
        'SEGUNDO_PARCIAL' => 'segundos_parciales',
        'FINAL' => 'finales_y_segunda_instancia',
        'SEGUNDA_INSTANCIA' => 'finales_y_segunda_instancia',
        'MESA' => 'examenes_de_mesa',
    ];

    public function __construct(
        private ConsultaExamenGateway $consulta,
        private PeriodoGateway $periodos,
        private PlantillaNormaGateway $plantillas,
    ) {}

    /**
     * Devuelve los datos listos para guardar: con el periodo resuelto y un
     * encargado en cada aula. Con `$soloPersonal` (examen con ingresos) lo
     * demas no cambia y no se vuelve a comprobar.
     *
     * @throws DatoInvalidoException
     */
    public function execute(
        GuardarExamenData $datos,
        int $usuarioId,
        ?ExamenActualData $actual = null,
        bool $soloPersonal = false,
    ): GuardarExamenData {
        $periodoId = $actual !== null && $soloPersonal
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
            aulas: $this->aulasConPersonal($datos->aulas, $usuarioId),
            periodoId: $periodoId,
            normasMarcadas: $datos->normasMarcadas,
            normasConservadas: $datos->normasConservadas,
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
        $ventanas = [];

        foreach ($this->periodos->vigentes() as $periodo) {
            $vigentes[] = $periodo->id;
            $ventanas[$periodo->id] = $periodo->ventanas;
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
                'Sin grupos propios de esta asignatura en un período vigente.',
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
            throw new DatoInvalidoException('grupos', 'Elija al menos un grupo.');
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

        $this->comprobarFechaYHora($datos, $actual, $ventanas[$primero->periodoId] ?? []);

        $ocupados = $this->consulta->gruposConExamenDeTipo($datos->grupos, $datos->tipo, $actual?->id);

        if ($ocupados !== []) {
            throw new GrupoYaTieneExamenException(
                $ocupados[0],
                AvanceDeExamen::tipoConArticulo(TipoExamen::from($datos->tipo)),
            );
        }

        return $primero->periodoId;
    }

    /**
     * Un examen nuevo (o al que se le cambia la fecha o la hora) no queda
     * en el pasado; empieza en horario de clases y termina el mismo dia; y,
     * si el calendario del periodo trae la ventana de su tipo, cae dentro.
     *
     * @param  array<string, mixed>  $ventanas
     *
     * @throws DatoInvalidoException
     */
    private function comprobarFechaYHora(GuardarExamenData $datos, ?ExamenActualData $actual, array $ventanas): void
    {
        $cambiaCuando = $actual === null
            || substr($actual->fecha, 0, 10) !== substr($datos->fecha, 0, 10)
            || substr($actual->horaInicio, 0, 5) !== substr($datos->horaInicio, 0, 5);

        if (! $cambiaCuando) {
            return;
        }

        $hoy = now()->toDateString();

        if ($datos->fecha < $hoy) {
            throw new DatoInvalidoException('fecha', 'La fecha ya pasó.');
        }

        $inicio = $this->minutos($datos->horaInicio);

        if ($inicio < self::DESDE || $inicio > self::HASTA) {
            throw new DatoInvalidoException('hora_inicio', 'Entre 06:00 y 22:00.');
        }

        if ($datos->fecha === $hoy && $inicio < $this->minutos(now()->format('H:i'))) {
            throw new DatoInvalidoException('hora_inicio', 'La hora ya pasó.');
        }

        if ($inicio + $datos->duracionMinutos > 24 * 60) {
            throw new DatoInvalidoException('duracion_minutos', 'El examen terminaría después de medianoche.');
        }

        $ventana = $ventanas[self::VENTANA_DE_TIPO[$datos->tipo] ?? ''] ?? null;

        if (
            is_array($ventana) && count($ventana) === 2
            && is_string($ventana[0]) && is_string($ventana[1])
            && ($datos->fecha < $ventana[0] || $datos->fecha > $ventana[1])
        ) {
            throw new DatoInvalidoException('fecha', sprintf(
                'Fuera del calendario: %s del %s al %s.',
                mb_strtolower(TipoExamen::from($datos->tipo)->etiqueta()),
                $this->diaMes($ventana[0]),
                $this->diaMes($ventana[1]),
            ));
        }
    }

    private function minutos(string $hora): int
    {
        [$h, $m] = array_map(intval(...), array_pad(explode(':', $hora), 2, '0'));

        return $h * 60 + $m;
    }

    private function diaMes(string $fecha): string
    {
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

        return (int) substr($fecha, 8, 2).' '.($meses[(int) substr($fecha, 5, 2) - 1] ?? '');
    }

    /**
     * Encargado: un docente o un auxiliar (por defecto, quien registra).
     * Apoyo: auxiliares, distintos del encargado.
     *
     * @param  list<AulaDeExamenData>  $aulas
     * @return list<AulaDeExamenData>
     */
    private function aulasConPersonal(array $aulas, int $usuarioId): array
    {
        $ids = [];

        foreach ($aulas as $aula) {
            if ($aula->encargadoId !== null) {
                $ids[] = $aula->encargadoId;
            }

            foreach ($aula->apoyo as $apoyoId) {
                $ids[] = $apoyoId;
            }
        }

        $personas = $this->consulta->personas(array_values(array_unique($ids)));
        $resueltas = [];

        foreach ($aulas as $indice => $aula) {
            $encargadoId = $aula->encargadoId ?? $usuarioId;

            if ($encargadoId !== $usuarioId) {
                $encargado = $personas[$encargadoId] ?? null;

                if ($encargado === null || (! $encargado->esDocente && ! $encargado->esAuxiliar)) {
                    throw new DatoInvalidoException(
                        "aulas.{$indice}.encargado_id",
                        'El encargado debe ser una cuenta de docente o de auxiliar.',
                    );
                }
            }

            foreach ($aula->apoyo as $apoyoId) {
                if ($apoyoId === $encargadoId) {
                    throw new DatoInvalidoException(
                        "aulas.{$indice}.apoyo",
                        'El apoyo no puede ser el encargado del aula.',
                    );
                }

                $apoyo = $personas[$apoyoId] ?? null;

                if ($apoyo === null || ! $apoyo->esAuxiliar) {
                    throw new DatoInvalidoException(
                        "aulas.{$indice}.apoyo",
                        'El apoyo debe ser una cuenta de auxiliar.',
                    );
                }
            }

            $resueltas[] = new AulaDeExamenData($aula->aulaId, $encargadoId, $aula->apoyo);
        }

        return $resueltas;
    }
}
