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
    /**
     * Fotos de evidencia por incidencia, entre el reporte y las correcciones.
     */
    public const MAXIMO_EVIDENCIAS = 3;

    public function __construct(
        private IncidenciaGateway $incidencias,
        private AlcancePuerta $alcance,
        private AlmacenEvidencias $evidencias,
        private EstudianteDeIncidencia $estudiantes,
    ) {}

    /**
     * @param  list<string>  $imagenes  fotos que se agregan a la evidencia
     */
    public function ejecutar(
        int $incidenciaId,
        int $examenId,
        TipoIncidencia $actual,
        TipoIncidencia $tipo,
        string $detalle,
        array $imagenes,
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

        $libres = self::MAXIMO_EVIDENCIAS - $this->incidencias->cuantasEvidencias($incidenciaId);

        if (count($imagenes) > $libres) {
            throw new DatoNoValidoException('evidencias', $libres <= 0
                ? sprintf('Ya tiene %d fotos', self::MAXIMO_EVIDENCIAS)
                : sprintf('Máximo %d %s más', $libres, $libres === 1 ? 'foto' : 'fotos'));
        }

        $rutas = [];

        foreach ($imagenes as $imagen) {
            $ruta = $this->evidencias->guardar($imagen);

            if ($ruta === null) {
                // Las que ya se guardaron no quedan sueltas.
                array_map(fn (string $guardada) => $this->evidencias->borrar($guardada), $rutas);

                throw new DatoNoValidoException('evidencias', 'La imagen no se pudo leer.');
            }

            $rutas[] = $ruta;
        }

        // Las fotos se agregan: no reemplazan ni borran las que habia.
        $this->incidencias->corregir(
            $incidenciaId,
            $tipo,
            $detalle,
            $rutas,
            $usuarioId,
            $cambiaEstudiante ? ['texto' => $estudiante, 'id' => $estudianteId] : null,
        );
    }
}
