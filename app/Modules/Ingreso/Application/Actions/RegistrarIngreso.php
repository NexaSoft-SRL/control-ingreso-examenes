<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciaIngresoGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Application\Queries\ConsultarCandidato;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use App\Modules\Ingreso\Domain\Rules\EvaluarIngreso;
use DomainException;
use InvalidArgumentException;

final readonly class RegistrarIngreso
{
    public function __construct(
        private ExamenGateway $examenes,
        private ConsultarCandidato $candidatos,
        private IngresoGateway $ingresos,
        private AlcancePuntoControl $alcance,
        private VentanaPuntoControl $ventana,
        private EstadoExpulsionGateway $expulsiones,
        private EvaluarIngreso $evaluador,
        private IncidenciaIngresoGateway $incidencias,
    ) {}

    public function ejecutar(
        int $usuarioId,
        int $examenId,
        int $aulaId,
        string $tipoIdentificador,
        string $identificador,
    ): VeredictoIngreso {
        if ($usuarioId <= 0 || $examenId <= 0 || $aulaId <= 0) {
            throw new InvalidArgumentException('Identificadores inválidos.');
        }

        $examen = $this->examenes->actual($examenId);

        if ($examen === null) {
            throw new InvalidArgumentException('El examen no existe.');
        }

        if (! in_array($aulaId, $examen->aulas, true)) {
            throw new AccesoPuntoDenegado(
                'El aula no pertenece al examen.',
            );
        }

        if (! $this->alcance->puedeOperar(
            $usuarioId,
            $examenId,
            $aulaId,
        )) {
            throw new AccesoPuntoDenegado(
                'El operador no está autorizado para este punto.',
            );
        }

        if (! $this->ventana->estaAbierta($examenId)) {
            throw new DomainException(
                'El punto de control está fuera del horario permitido.',
            );
        }

        $candidato = $this->candidatos->consultar(
            $examenId,
            $tipoIdentificador,
            $identificador,
        );

        if ($candidato === null) {
            $this->incidencias->registrar(
                $examenId,
                $aulaId,
                null,
                $usuarioId,
                VeredictoIngreso::NoCorresponde,
                'Estudiante no encontrado.',
            );

            return VeredictoIngreso::NoCorresponde;
        }

        $estudianteId = $candidato->estudiante->id;

        $veredicto = $this->evaluador->evaluar(
            inscrito: $candidato->inscrito,
            habilitado: $candidato->habilitacion?->habilitado,
            aulaAsignadaId: $candidato->habilitacion?->aulaId,
            aulaControlId: $aulaId,
            yaIngreso: $this->ingresos->yaIngreso(
                $examenId,
                $estudianteId,
            ),
            expulsado: $this->expulsiones->estaExpulsado(
                $examenId,
                $estudianteId,
            ),
        );

        if (! $veredicto->permiteIngreso()) {
            $detalle = match ($veredicto) {
                VeredictoIngreso::NoHabilitado => $candidato->habilitacion === null
                        ? 'El estudiante no tiene condición de habilitación registrada para este examen.'
                        : (trim((string) $candidato->habilitacion->motivo) ?: 'Estudiante inhabilitado sin motivo disponible.'),
                VeredictoIngreso::OtraAula => 'El estudiante está asignado a otra aula.',
                VeredictoIngreso::YaIngreso => 'El estudiante ya tiene un ingreso registrado.',
                VeredictoIngreso::NoCorresponde => 'El estudiante no corresponde al examen o no tiene aula asignada.',
                VeredictoIngreso::Expulsado => 'El estudiante está expulsado del examen.',
                VeredictoIngreso::Admitido => throw new \LogicException('Una admisión no es un rechazo.'),
            };

            $this->incidencias->registrar(
                $examenId,
                $aulaId,
                $estudianteId,
                $usuarioId,
                $veredicto,
                $detalle,
            );

            return $veredicto;
        }

        $registrado = $this->ingresos->registrar(
            $examenId,
            $estudianteId,
            $aulaId,
            $usuarioId,
        );

        if (! $registrado) {
            $this->incidencias->registrar(
                $examenId,
                $aulaId,
                $estudianteId,
                $usuarioId,
                VeredictoIngreso::YaIngreso,
                'El estudiante ya tiene un ingreso registrado.',
            );

            return VeredictoIngreso::YaIngreso;
        }

        return VeredictoIngreso::Admitido;
    }
}
