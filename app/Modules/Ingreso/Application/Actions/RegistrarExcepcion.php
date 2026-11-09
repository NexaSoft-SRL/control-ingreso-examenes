<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\CondicionHabilitacionGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\Contracts\Transaccion;
use App\Modules\Ingreso\Application\DTOs\AulaData;
use App\Modules\Ingreso\Application\DTOs\IngresoData;
use App\Modules\Ingreso\Application\DTOs\NuevaIncidenciaData;
use App\Modules\Ingreso\Application\DTOs\NuevoIngresoData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Enums\MedioIdentificacion;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoExcepcion;
use App\Modules\Ingreso\Domain\Exceptions\AulaSinExamenException;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use App\Modules\Ingreso\Domain\Exceptions\IngresoNoPermitidoException;
use LogicException;

/**
 * La situacion excepcional de la puerta. Siempre queda como incidencia;
 * solo el cambio de aula admite al estudiante, con las mismas
 * comprobaciones que un ingreso normal salvo la del aula.
 */
final readonly class RegistrarExcepcion
{
    public function __construct(
        private PuertaGateway $puerta,
        private CondicionHabilitacionGateway $habilitaciones,
        private IngresoGateway $ingresos,
        private IncidenciaGateway $incidencias,
        private Transaccion $transaccion,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * @return array{incidencia_id: int, ingreso: array{id: int, hora: string}|null}
     */
    public function execute(
        AulaData $aula,
        TipoExcepcion $tipo,
        string $motivo,
        ?int $estudianteId,
        ?int $examenId,
        OperadorData $operador,
    ): array {
        $delDia = $this->puerta->examenesDelDia($aula->id);

        if (! $this->alcance->atiendeAula($operador, $aula->id, $delDia)) {
            throw FueraDeAlcanceException::aula();
        }

        $abiertos = [];

        foreach ($delDia as $examen) {
            if ($examen->abierto) {
                $abiertos[] = $examen->id;
            }
        }

        if ($abiertos === []) {
            throw new AulaSinExamenException;
        }

        if ($examenId !== null && ! in_array($examenId, $abiertos, true)) {
            throw new DatoNoValidoException('examen_id', 'El examen no está abierto en esta aula.');
        }

        // Sin examen indicado, la incidencia queda en el primero del aula.
        $examenId ??= $abiertos[0];

        if ($tipo->admite()) {
            if ($estudianteId === null) {
                throw new DatoNoValidoException('estudiante_id', 'Indique el estudiante.');
            }

            $this->comprobarAdmision($examenId, $estudianteId);
        }

        return $this->transaccion->ejecutar(function () use (
            $aula, $tipo, $motivo, $estudianteId, $examenId, $operador,
        ): array {
            $ingreso = null;

            if ($tipo->admite() && $estudianteId !== null) {
                $ingreso = $this->ingresos->registrar(new NuevoIngresoData(
                    examenId: $examenId,
                    estudianteId: $estudianteId,
                    aulaId: $aula->id,
                    medio: MedioIdentificacion::Codigo,
                    esExcepcion: true,
                    operador: $operador,
                ));

                if (! $ingreso instanceof IngresoData) {
                    throw $this->yaIngreso();
                }
            }

            $incidenciaId = $this->incidencias->registrar(new NuevaIncidenciaData(
                tipo: $tipo->tipoIncidencia(),
                origen: OrigenIncidencia::Persona,
                detalle: $motivo,
                operador: $operador,
                examenId: $examenId,
                aulaId: $aula->id,
                estudianteId: $estudianteId,
                ingresoId: $ingreso?->id,
            ), 'incidencia.registrar');

            if ($incidenciaId === null) {
                throw new LogicException('La incidencia no se pudo asentar.');
            }

            return [
                'incidencia_id' => $incidenciaId,
                'ingreso' => $ingreso === null
                    ? null
                    : ['id' => $ingreso->id, 'hora' => $ingreso->horaCorta()],
            ];
        });
    }

    private function comprobarAdmision(int $examenId, int $estudianteId): void
    {
        if ($this->puerta->examenesDondeEstaInscrito($estudianteId, [$examenId]) === []) {
            throw new IngresoNoPermitidoException(
                'El estudiante no está inscrito en este examen.',
                'NO_INSCRITO',
            );
        }

        $previo = $this->ingresos->deEstudiante($examenId, $estudianteId);

        if ($previo !== null) {
            throw $previo->expulsadoA === null
                ? $this->yaIngreso()
                : new IngresoNoPermitidoException('El estudiante fue expulsado de este examen.', 'EXPULSADO');
        }

        $condicion = $this->habilitaciones->condicionDe($examenId, $estudianteId);

        if ($condicion === null || ! $condicion->habilitado) {
            throw new IngresoNoPermitidoException(
                'El estudiante no está habilitado para este examen.',
                'NO_HABILITADO',
            );
        }
    }

    private function yaIngreso(): IngresoNoPermitidoException
    {
        return new IngresoNoPermitidoException('El estudiante ya ingresó a este examen.', 'YA_INGRESO');
    }
}
