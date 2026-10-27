<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Contracts\DetallePuertaGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciaIngresoGateway;
use App\Modules\Ingreso\Application\DTOs\OperadorPuertaData;
use App\Modules\Ingreso\Application\DTOs\ResultadoResolucionPuertaData;
use App\Modules\Ingreso\Domain\Enums\CausaIntento;

/**
 * HU-19, criterio 6: el intento de quien no puede entrar queda registrado
 * y distinguido (no habilitado, otra aula, no inscrito, sin registro, QR no
 * valido) en las incidencias de ingreso del aula. Una identificacion que
 * se puede admitir no deja nada.
 */
final readonly class RegistrarIntentoEnPuerta
{
    public function __construct(
        private IncidenciaIngresoGateway $incidencias,
        private DetallePuertaGateway $detalle,
    ) {}

    public function ejecutar(
        OperadorPuertaData $operador,
        int $aulaId,
        string $tipo,
        string $identificador,
        ResultadoResolucionPuertaData $resultado,
    ): void {
        if ($resultado->puedeAdmitir()) {
            return;
        }

        if ($resultado->causa !== null) {
            $this->registrarIntento($operador, $aulaId, $tipo, $identificador, $resultado);

            return;
        }

        $veredicto = $resultado->veredicto;

        if ($resultado->examenId === null || $veredicto === null) {
            return;
        }

        $estudianteId = $resultado->candidato?->estudiante->id;
        $texto = RegistrarIngreso::detalleDeRechazo(
            $veredicto,
            $resultado->candidato?->habilitacion,
        );

        if ($this->incidencias->repetidoReciente(
            $aulaId,
            $estudianteId,
            $veredicto->value,
            $texto,
            RegistrarIngreso::REPETIDO_MINUTOS,
        )) {
            return;
        }

        $this->incidencias->registrar(
            $resultado->examenId,
            $aulaId,
            $estudianteId,
            $operador->usuarioId,
            $veredicto,
            $texto,
            $operador->accesoId,
        );
    }

    private function registrarIntento(
        OperadorPuertaData $operador,
        int $aulaId,
        string $tipo,
        string $identificador,
        ResultadoResolucionPuertaData $resultado,
    ): void {
        $causa = $resultado->causa;

        if ($causa === null) {
            return;
        }

        $aula = $this->detalle->aula($aulaId)['nombre'] ?? '';

        $texto = match ($causa) {
            CausaIntento::NoInscrito => sprintf(
                'No inscrito en los exámenes del aula %s.',
                $aula,
            ),
            // Un codigo universitario se muestra; un C.I. no se guarda.
            CausaIntento::NoRegistrado => match ($tipo) {
                'codigo' => sprintf('Código %s sin registro en el padrón.', trim($identificador)),
                'documento' => 'Documento sin registro en el padrón.',
                default => 'Código SIS o C.I. sin registro en el padrón.',
            },
            CausaIntento::QrNoValido => 'Código QR no válido.',
            default => $causa->etiqueta().'.',
        };

        $estudianteId = $resultado->estudiante?->id;

        // Sin estudiante (sin registro, QR) cada lectura es un intento.
        if ($estudianteId !== null && $this->incidencias->repetidoReciente(
            $aulaId,
            $estudianteId,
            $causa->value,
            $texto,
            RegistrarIngreso::REPETIDO_MINUTOS,
        )) {
            return;
        }

        $this->incidencias->registrarIntento(
            $aulaId,
            $estudianteId,
            $operador->usuarioId,
            $causa,
            $texto,
            $operador->accesoId,
        );
    }
}
