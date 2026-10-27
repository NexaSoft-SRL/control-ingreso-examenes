<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\OperacionesPuertaGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Application\Queries\ExamenesOperablesEnAula;
use App\Modules\Ingreso\Domain\Enums\TipoExcepcion;
use DomainException;
use InvalidArgumentException;

/**
 * Situacion excepcional en la puerta (N1). Siempre queda como incidencia
 * con su motivo, autor y hora; solo el cambio de aula admite al estudiante,
 * con las mismas comprobaciones que un ingreso normal salvo la del aula. A
 * un no habilitado no lo deja entrar.
 */
final readonly class RegistrarExcepcionEnPuerta
{
    public function __construct(
        private ExamenesOperablesEnAula $operables,
        private VentanaPuntoControl $ventana,
        private HabilitacionGateway $habilitaciones,
        private IngresoGateway $ingresos,
        private EstadoExpulsionGateway $expulsiones,
        private OperacionesPuertaGateway $operaciones,
    ) {}

    /**
     * @return array{examen_id: int, incidencia_id: int, ingreso: array{id: int, hora: string}|null}
     */
    public function ejecutar(
        int $usuarioId,
        int $aulaId,
        string $fecha,
        TipoExcepcion $tipo,
        string $motivo,
        ?int $estudianteId,
        ?int $examenId,
    ): array {
        $abiertos = array_values(array_filter(
            $this->operables->ejecutar($usuarioId, $aulaId, $fecha),
            fn (int $id): bool => $this->ventana->estaAbierta($id),
        ));

        if ($abiertos === []) {
            throw new DomainException('Sin examen a esta hora.');
        }

        if ($examenId !== null && ! in_array($examenId, $abiertos, true)) {
            throw new InvalidArgumentException('El examen no está abierto en esta aula.');
        }

        // Sin examen indicado (no inscrito, QR no valido), la incidencia
        // queda en el primer examen abierto del aula.
        $examenId ??= $abiertos[0];

        if ($tipo->admite()) {
            if ($estudianteId === null) {
                throw new InvalidArgumentException('Indique el estudiante.');
            }

            $this->comprobarAdmision($examenId, $estudianteId);
        }

        $resultado = $this->operaciones->registrarExcepcion(
            $examenId,
            $aulaId,
            $estudianteId,
            $usuarioId,
            $tipo,
            $motivo,
        );

        if ($resultado === null) {
            throw new DomainException('El estudiante ya ingresó a este examen.');
        }

        return ['examen_id' => $examenId, ...$resultado];
    }

    private function comprobarAdmision(int $examenId, int $estudianteId): void
    {
        if (! $this->operaciones->estudiantePerteneceAlExamen($examenId, $estudianteId)) {
            throw new DomainException('El estudiante no está inscrito en este examen.');
        }

        if ($this->expulsiones->estaExpulsado($examenId, $estudianteId)) {
            throw new DomainException('El estudiante fue expulsado de este examen.');
        }

        if ($this->ingresos->yaIngreso($examenId, $estudianteId)) {
            throw new DomainException('El estudiante ya ingresó a este examen.');
        }

        $condicion = $this->habilitaciones->condicionDe($examenId, $estudianteId);

        if ($condicion === null || ! $condicion->habilitado) {
            throw new DomainException('El estudiante no está habilitado para este examen.');
        }
    }
}
