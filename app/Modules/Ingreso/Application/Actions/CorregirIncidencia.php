<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AlmacenImagenes;
use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\AlmacenVideos;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\DTOs\VideoData;
use App\Modules\Ingreso\Domain\Enums\Gravedad;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;

/**
 * El docente del examen corrige una incidencia: su tipo, su detalle, el
 * estudiante y la foto que la respalda. La expulsion y el ingreso repetido conservan su
 * tipo, porque los asienta otra operacion.
 */
final readonly class CorregirIncidencia
{
    public const LADO_EVIDENCIA = 1280;

    /**
     * Fotos de evidencia por incidencia, entre el reporte y las correcciones.
     */
    public const MAXIMO_EVIDENCIAS = 3;

    public function __construct(
        private IncidenciaGateway $incidencias,
        private AlcancePuesto $alcance,
        private AlmacenImagenes $imagenes,
        private EstudianteDeIncidencia $estudiantes,
        private AlmacenVideos $videos,
    ) {}

    /**
     * @param  list<string>  $imagenes  fotos que se agregan a la evidencia
     */
    public function execute(
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
        ?Gravedad $gravedad = null,
        ?string $tipoTexto = null,
        ?VideoData $video = null,
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

        if ($video !== null && $this->incidencias->video($incidenciaId) !== null) {
            throw new DatoNoValidoException('video', 'Ya tiene un video');
        }

        $rutas = [];

        foreach ($imagenes as $imagen) {
            $ruta = $this->imagenes->guardar('incidencias', bin2hex(random_bytes(16)), $imagen, self::LADO_EVIDENCIA);

            if ($ruta === null) {
                array_map(fn (string $guardada) => $this->imagenes->borrar($guardada), $rutas);

                throw new DatoNoValidoException('evidencias', 'La imagen no se pudo leer.');
            }

            $rutas[] = $ruta;
        }

        $this->incidencias->corregir(
            $incidenciaId,
            $tipo,
            $detalle,
            $rutas,
            $usuarioId,
            $cambiaEstudiante ? ['texto' => $estudiante, 'id' => $estudianteId] : null,
            $gravedad,
            $tipoTexto,
        );

        if ($video !== null) {
            $ruta = $this->videos->guardar($video->contenido, $video->tipo);

            if ($ruta === null) {
                throw new DatoNoValidoException('video', 'El video no se pudo guardar.');
            }

            if (! $this->incidencias->ponerVideo($incidenciaId, $ruta, $video->tipo, $usuarioId)) {
                $this->videos->borrar($ruta);
            }
        }
    }
}
