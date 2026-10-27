<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Habilitacion\Application\DTOs\HabilitacionData;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\DetallePuertaGateway;
use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciaIngresoGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Application\DTOs\ResultadoIngresoData;
use App\Modules\Ingreso\Application\Queries\ConsultarCandidato;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use App\Modules\Ingreso\Domain\Rules\EvaluarIngreso;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use LogicException;

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
        private DetallePuertaGateway $detalle,
    ) {}

    public function ejecutar(
        ?int $usuarioId,
        int $examenId,
        int $aulaId,
        string $tipoIdentificador,
        string $identificador,
        ?int $accesoCodigoAulaId = null,
    ): VeredictoIngreso {
        return $this->ejecutarDetallado(
            $usuarioId,
            $examenId,
            $aulaId,
            $tipoIdentificador,
            $identificador,
            $accesoCodigoAulaId,
        )->veredicto;
    }

    /**
     * Como `ejecutar`, y ademas dice si quedo asentado un ingreso repetido.
     * `$porQr` solo cambia el texto de esa incidencia.
     */
    public function ejecutarDetallado(
        ?int $usuarioId,
        int $examenId,
        int $aulaId,
        string $tipoIdentificador,
        string $identificador,
        ?int $accesoCodigoAulaId = null,
        bool $porQr = false,
    ): ResultadoIngresoData {
        if (($usuarioId === null && $accesoCodigoAulaId === null) || $examenId <= 0 || $aulaId <= 0) {
            throw new InvalidArgumentException('Identificadores inválidos.');
        }

        if ($usuarioId !== null && $usuarioId <= 0) {
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

        if ($usuarioId !== null && ! $this->alcance->puedeOperar(
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
            $this->registrarRechazo(
                $examenId,
                $aulaId,
                null,
                $usuarioId,
                VeredictoIngreso::NoCorresponde,
                'Estudiante no encontrado.',
                $accesoCodigoAulaId,
            );

            return new ResultadoIngresoData(VeredictoIngreso::NoCorresponde);
        }

        $estudianteId = $candidato->estudiante->id;

        $veredicto = $this->evaluador->evaluar(
            inscrito: $candidato->inscrito,
            habilitado: $candidato->habilitacion?->habilitado,
            yaIngreso: $this->ingresos->yaIngreso(
                $examenId,
                $estudianteId,
            ),
            expulsado: $this->expulsiones->estaExpulsado(
                $examenId,
                $estudianteId,
            ),
        );

        if ($veredicto === VeredictoIngreso::YaIngreso) {
            return new ResultadoIngresoData(
                $veredicto,
                $this->registrarRepetido($examenId, $aulaId, $estudianteId, $usuarioId, $accesoCodigoAulaId, $porQr),
            );
        }

        if (! $veredicto->permiteIngreso()) {
            $this->registrarRechazo(
                $examenId,
                $aulaId,
                $estudianteId,
                $usuarioId,
                $veredicto,
                self::detalleDeRechazo($veredicto, $candidato->habilitacion),
                $accesoCodigoAulaId,
            );

            return new ResultadoIngresoData($veredicto);
        }

        $registrado = $this->ingresos->registrar(
            $examenId,
            $estudianteId,
            $aulaId,
            $usuarioId,
            $accesoCodigoAulaId,
        );

        if (! $registrado) {
            // Otro puesto lo registro entre la comprobacion y la insercion:
            // no es un intento de quien esta en esta puerta.
            return new ResultadoIngresoData(VeredictoIngreso::YaIngreso);
        }

        return new ResultadoIngresoData(VeredictoIngreso::Admitido);
    }

    /**
     * Una identificacion que ya entro (un QR prestado, alguien que se hace
     * pasar por otro) queda como incidencia de ingreso repetido. No la deja
     * la relectura en la misma puerta enseguida de admitirlo, ni una
     * segunda lectura del mismo ingreso en los ultimos minutos.
     */
    private function registrarRepetido(
        int $examenId,
        int $aulaId,
        int $estudianteId,
        ?int $usuarioId,
        ?int $accesoCodigoAulaId,
        bool $porQr,
    ): bool {
        $previo = $this->detalle->ingresoDe($examenId, $estudianteId);

        if ($previo === null) {
            return false;
        }

        $zona = new DateTimeZone('America/La_Paz');
        $segundos = (new DateTimeImmutable('now', $zona))->getTimestamp()
            - (new DateTimeImmutable(substr($previo['registrado_en'], 0, 19), $zona))->getTimestamp();

        if (
            $this->evaluador->esRelectura($previo['aula_id'], $aulaId, $segundos)
            || $this->incidencias->ingresoRepetidoReciente($previo['id'], $aulaId, self::REPETIDO_MINUTOS)
        ) {
            return false;
        }

        $this->incidencias->registrarIngresoRepetido(
            $examenId,
            $aulaId,
            $estudianteId,
            $previo['id'],
            $usuarioId,
            $accesoCodigoAulaId,
            sprintf(
                '%s ya usado. %s.',
                $porQr ? 'Código QR' : 'Código o C.I.',
                self::yaIngresoTexto($previo['hora'], $previo['aula_id'] === $aulaId ? null : $previo['aula']),
            ),
        );

        return true;
    }

    // «Ingresó a las 08:39 en esta aula» o «… en el aula 691A».
    public static function yaIngresoTexto(string $hora, ?string $otraAula): string
    {
        return sprintf(
            'Ingresó a las %s %s',
            $hora,
            $otraAula === null ? 'en esta aula' : 'en el aula '.$otraAula,
        );
    }

    // Leer otra vez a la misma persona en la misma puerta no suma
    // incidencias: una por intento cada diez minutos.
    public const REPETIDO_MINUTOS = 10;

    public static function detalleDeRechazo(
        VeredictoIngreso $veredicto,
        ?HabilitacionData $habilitacion,
    ): string {
        return match ($veredicto) {
            VeredictoIngreso::NoHabilitado => $habilitacion === null
                    ? 'Sin revisar: sin condición de habilitación registrada.'
                    : (trim((string) $habilitacion->motivo) ?: 'No habilitado, sin motivo registrado.'),
            VeredictoIngreso::YaIngreso => 'El estudiante ya tiene un ingreso registrado.',
            VeredictoIngreso::NoCorresponde => 'El estudiante no corresponde al examen.',
            VeredictoIngreso::Expulsado => 'El estudiante está expulsado del examen.',
            VeredictoIngreso::Admitido => throw new LogicException('Una admisión no es un rechazo.'),
        };
    }

    private function registrarRechazo(
        int $examenId,
        int $aulaId,
        ?int $estudianteId,
        ?int $usuarioId,
        VeredictoIngreso $veredicto,
        string $detalle,
        ?int $accesoCodigoAulaId,
    ): void {
        if ($this->incidencias->repetidoReciente(
            $aulaId,
            $estudianteId,
            $veredicto->value,
            $detalle,
            self::REPETIDO_MINUTOS,
        )) {
            return;
        }

        $this->incidencias->registrar(
            $examenId,
            $aulaId,
            $estudianteId,
            $usuarioId,
            $veredicto,
            $detalle,
            $accesoCodigoAulaId,
        );
    }
}
