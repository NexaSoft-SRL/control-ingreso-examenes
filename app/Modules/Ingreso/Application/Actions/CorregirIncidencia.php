<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuerta;
use App\Modules\Ingreso\Application\Contracts\AlmacenEvidencias;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;

/**
 * El docente del examen corrige una incidencia: su tipo, su detalle, el
 * estudiante y la foto que la respalda. La expulsion y el ingreso repetido
 * conservan su tipo, porque los asienta otra operacion.
 */
final readonly class CorregirIncidencia
{
    public function __construct(
        private IncidenciaGateway $incidencias,
        private AlcancePuerta $alcance,
        private AlmacenEvidencias $evidencias,
        private EstudianteDeIncidencia $estudiantes,
    ) {}

    public function ejecutar(
        int $incidenciaId,
        int $examenId,
        TipoIncidencia $actual,
        TipoIncidencia $tipo,
        string $detalle,
        ?string $imagen,
        int $usuarioId,
        bool $cambiaEstudiante = false,
        ?string $estudiante = null,
        ?string $detalleActual = null,
    ): void {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        $fijos = [TipoIncidencia::Expulsion, TipoIncidencia::IngresoRepetido];

        if ($tipo !== $actual && (in_array($actual, $fijos, true) || in_array($tipo, $fijos, true))) {
            throw new DatoNoValidoException('tipo', 'Este tipo no se puede cambiar.');
        }

        // El ingreso repetido lo escribe la puerta: su texto es el registro
        // de lo que paso y no se reescribe.
        if ($actual === TipoIncidencia::IngresoRepetido && $detalleActual !== null) {
            $detalle = $detalleActual;
        }

        $estudianteId = $cambiaEstudiante && $estudiante !== null
            ? $this->estudiantes->resolver($examenId, trim($estudiante))
            : null;

        $ruta = null;

        if ($imagen !== null) {
            $ruta = $this->evidencias->guardar($imagen);

            if ($ruta === null) {
                throw new DatoNoValidoException('evidencia', 'La imagen no se pudo leer.');
            }
        }

        $anterior = $this->incidencias->corregir(
            $incidenciaId,
            $tipo,
            $detalle,
            $ruta,
            $usuarioId,
            $cambiaEstudiante ? ['texto' => $estudiante, 'id' => $estudianteId] : null,
        );

        if ($anterior !== null) {
            $this->evidencias->borrar($anterior);
        }
    }
}
