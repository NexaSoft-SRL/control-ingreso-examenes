<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AlmacenImagenes;
use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciasComunesGateway;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\DTOs\ExamenData;
use App\Modules\Ingreso\Application\DTOs\NuevaIncidenciaData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Enums\Gravedad;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeFechaException;
use LogicException;

/**
 * Alguien reporta una incidencia en un aula del examen. Solo el dia del
 * examen; el auxiliar, solo en su aula.
 */
final readonly class ReportarIncidencia
{
    public function __construct(
        private PuertaGateway $puerta,
        private IncidenciaGateway $incidencias,
        private AlcancePuesto $alcance,
        private AlmacenImagenes $imagenes,
        private EstudianteDeIncidencia $estudiantes,
        private IncidenciasComunesGateway $comunes,
    ) {}

    /**
     * La gravedad la elige quien registra; si no la elige, se propone la de
     * la lista de incidencias comunes del docente (o la del tipo). Una no
     * prevista va con tipo OTRA y su nombre en `$tipoTexto`; con
     * `$guardarComun` queda en la lista de quien la registra.
     *
     * @param  list<string>  $imagenes  hasta tres fotos de evidencia
     * @return array<string, mixed>
     */
    public function execute(
        ExamenData $examen,
        int $aulaId,
        TipoIncidencia $tipo,
        ?string $estudiante,
        string $detalle,
        OperadorData $operador,
        array $imagenes = [],
        ?Gravedad $gravedad = null,
        ?string $tipoTexto = null,
        bool $guardarComun = false,
    ): array {
        if (! $this->puerta->examenTieneAula($examen->id, $aulaId)) {
            throw new DatoNoValidoException('aula_id', 'El aula no es de este examen.');
        }

        if (! $this->alcance->actuaEnExamen($operador, $examen, $aulaId)) {
            throw FueraDeAlcanceException::aula();
        }

        if (! $examen->esHoy) {
            throw new FueraDeFechaException;
        }

        $texto = $estudiante === null ? '' : trim($estudiante);
        $identificado = $this->estudiantes->resolver($examen->id, $texto);

        if (count($imagenes) > CorregirIncidencia::MAXIMO_EVIDENCIAS) {
            throw new DatoNoValidoException('evidencias', sprintf('Máximo %d fotos', CorregirIncidencia::MAXIMO_EVIDENCIAS));
        }

        $evidencias = [];

        foreach ($imagenes as $imagen) {
            $ruta = $this->imagenes->guardar(
                'incidencias',
                bin2hex(random_bytes(16)),
                $imagen,
                CorregirIncidencia::LADO_EVIDENCIA,
            );

            if ($ruta === null) {
                // Las que ya se guardaron no quedan sueltas.
                array_map(fn (string $guardada) => $this->imagenes->borrar($guardada), $evidencias);

                throw new DatoNoValidoException('evidencias', 'La imagen no se pudo leer.');
            }

            $evidencias[] = $ruta;
        }

        $nombre = $tipo === TipoIncidencia::Otra && $tipoTexto !== null && trim($tipoTexto) !== '' ? trim($tipoTexto) : null;
        $dueno = $this->comunes->duenoParaExamen($examen->id, $operador->usuarioId);
        $gravedad ??= ($nombre === null ? null : $this->comunes->gravedadDeNombre($dueno, $nombre))
            ?? $this->comunes->gravedadDeTipo($dueno, $tipo);

        if ($guardarComun && $operador->usuarioId !== null) {
            if ($nombre !== null) {
                $this->comunes->guardarPropia($operador->usuarioId, $nombre, $gravedad);
            } elseif ($tipo !== TipoIncidencia::Otra) {
                $this->comunes->guardarTipo($operador->usuarioId, $tipo, $gravedad);
            }
        }

        $incidenciaId = $this->incidencias->registrar(new NuevaIncidenciaData(
            tipo: $tipo,
            origen: OrigenIncidencia::Persona,
            detalle: $detalle,
            operador: $operador,
            examenId: $examen->id,
            aulaId: $aulaId,
            estudianteId: $identificado,
            estudianteTexto: $texto === '' ? null : $texto,
            evidencias: $evidencias,
            gravedad: $gravedad,
            tipoTexto: $nombre,
        ), 'incidencia.registrar');

        $fila = $incidenciaId === null ? null : $this->incidencias->reportada($incidenciaId);

        if ($fila === null) {
            throw new LogicException('La incidencia no se pudo asentar.');
        }

        unset($fila['examen_id']);

        return $fila;
    }
}
