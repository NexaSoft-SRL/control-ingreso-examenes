<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuerta;
use App\Modules\Ingreso\Application\Contracts\AlmacenEvidencias;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\DTOs\NuevaIncidenciaData;
use App\Modules\Ingreso\Application\DTOs\OperadorPuertaData;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use LogicException;

/**
 * Alguien reporta una incidencia en un aula del examen: el docente, quien
 * atiende esa aula o quien la controla con su codigo. Solo el dia del
 * examen.
 */
final readonly class ReportarIncidencia
{
    public function __construct(
        private IncidenciaGateway $incidencias,
        private AlcancePuerta $alcance,
        private AlmacenEvidencias $evidencias,
        private EstudianteDeIncidencia $estudiantes,
    ) {}

    /**
     * @param  list<string>  $imagenes  hasta tres fotos de evidencia
     * @return array<string, mixed>
     */
    public function ejecutar(
        int $examenId,
        int $aulaId,
        TipoIncidencia $tipo,
        ?string $estudiante,
        string $detalle,
        OperadorPuertaData $operador,
        array $imagenes = [],
    ): array {
        if (! $this->incidencias->examenTieneAula($examenId, $aulaId)) {
            throw new DatoNoValidoException('aula_id', 'El aula no es de este examen.');
        }

        if (! $this->alcance->actuaEnAula($operador, $examenId, $aulaId)) {
            throw new AccesoPuntoDenegado('Sin asignación en esta aula.');
        }

        $hoy = (new DateTimeImmutable('now', new DateTimeZone('America/La_Paz')))->format('Y-m-d');

        if (substr((string) $this->incidencias->fechaDelExamen($examenId), 0, 10) !== $hoy) {
            throw new DomainException('Solo el día del examen.');
        }

        $texto = $estudiante === null ? '' : trim($estudiante);
        $identificado = $this->estudiantes->resolver($examenId, $texto);

        if (count($imagenes) > CorregirIncidencia::MAXIMO_EVIDENCIAS) {
            throw new DatoNoValidoException('evidencias', sprintf('Máximo %d fotos', CorregirIncidencia::MAXIMO_EVIDENCIAS));
        }

        $evidencias = [];

        foreach ($imagenes as $imagen) {
            $ruta = $this->evidencias->guardar($imagen);

            if ($ruta === null) {
                // Las que ya se guardaron no quedan sueltas.
                array_map(fn (string $guardada) => $this->evidencias->borrar($guardada), $evidencias);

                throw new DatoNoValidoException('evidencias', 'La imagen no se pudo leer.');
            }

            $evidencias[] = $ruta;
        }

        $incidenciaId = $this->incidencias->registrar(new NuevaIncidenciaData(
            tipo: $tipo,
            origen: OrigenIncidencia::Persona,
            detalle: $detalle,
            operador: $operador,
            examenId: $examenId,
            aulaId: $aulaId,
            estudianteId: $identificado,
            estudianteTexto: $texto === '' ? null : $texto,
            evidencias: $evidencias,
        ), 'incidencia.registrar');

        $fila = $incidenciaId === null ? null : $this->incidencias->reportada($incidenciaId);

        if ($fila === null) {
            throw new LogicException('La incidencia no se pudo asentar.');
        }

        unset($fila['examen_id']);

        return $fila;
    }
}
