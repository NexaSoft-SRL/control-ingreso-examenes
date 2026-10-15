<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Estudiantes\Application\Contracts\InscritosDeExamenGateway;
use App\Modules\Estudiantes\Application\Contracts\PadronGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Ingreso\Application\DTOs\CandidatoIngresoData;
use InvalidArgumentException;

final readonly class ConsultarCandidato
{
    public function __construct(
        private PadronGateway $padron,
        private InscritosDeExamenGateway $inscritos,
        private ExamenGateway $examenes,
        private HabilitacionGateway $habilitaciones,
    ) {}

    public function consultar(
        int $examenId,
        string $tipo,
        string $identificador,
    ): ?CandidatoIngresoData {
        $identificador = trim($identificador);

        if ($examenId <= 0 || $identificador === '') {
            throw new InvalidArgumentException('Identificador o examen inválido.');
        }

        if (! in_array($tipo, ['codigo', 'documento'], true)) {
            throw new InvalidArgumentException('Tipo de identificación inválido.');
        }

        $examen = $this->examenes->actual($examenId);

        if ($examen === null) {
            throw new InvalidArgumentException('El examen no existe.');
        }

        $codigo = $identificador;

        if ($tipo === 'documento') {
            $codigos = $this->padron->codigosPorDocumento([$identificador]);
            $codigo = $codigos[$identificador] ?? '';

            if ($codigo === '') {
                return null;
            }
        }

        $estudiantes = $this->padron->porCodigos([$codigo]);
        $estudiante = $estudiantes[$codigo] ?? null;

        if ($estudiante === null) {
            return null;
        }

        $inscritos = $this->inscritos->estudiantesDe($examen->grupos);
        $estaInscrito = false;

        foreach ($inscritos as $inscrito) {
            if ($inscrito->id === $estudiante->id) {
                $estaInscrito = true;
                break;
            }
        }

        $habilitacion = $estaInscrito
            ? $this->habilitaciones->condicionDe($examenId, $estudiante->id)
            : null;

        return new CandidatoIngresoData(
            $estudiante,
            $estaInscrito,
            $habilitacion,
        );
    }
}
