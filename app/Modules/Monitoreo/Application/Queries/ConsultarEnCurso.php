<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\Queries;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\DTOs\AulaDetalleData;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Monitoreo\Application\Contracts\EnCursoGateway;
use App\Modules\Monitoreo\Application\DTOs\AulaEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\AvanceEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\ExamenEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\IncidenciaEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\TableroEnCursoData;
use App\Modules\Monitoreo\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Monitoreo\Domain\Exceptions\ExamenNoEncontradoException;
use LogicException;

/**
 * El examen en curso, aula por aula (HU-22). Solo lo ve un docente del
 * examen. Quien atiende cada aula y su codigo salen del examen (HU-18);
 * el aula compartida, de la misma regla que usa el registro del examen;
 * si el ingreso esta abierto, de la ventana de la puerta (HU-19).
 */
final readonly class ConsultarEnCurso
{
    public function __construct(
        private ExamenGateway $examenes,
        private ConsultaExamenGateway $consulta,
        private AlcanceExamenGateway $alcance,
        private VentanaPuntoControl $ventana,
        private EnCursoGateway $enCurso,
    ) {}

    public function ejecutar(int $usuarioId, int $examenId): TableroEnCursoData
    {
        if (! $this->examenes->existe($examenId)) {
            throw new ExamenNoEncontradoException('Examen no encontrado.');
        }

        $esDocente = $this->alcance->esDocenteDelExamen($usuarioId, $examenId);
        $mias = $this->alcance->aulasQueAtiende($usuarioId, $examenId);

        if (! $esDocente && $mias === []) {
            throw new ExamenAjenoException('Este examen no es suyo.');
        }

        $detalle = $this->consulta->detalle($examenId, $usuarioId);

        if ($detalle === null) {
            throw new LogicException('El examen existe, pero no se pudo leer.');
        }

        $resumen = $detalle->resumen;
        $avance = $this->enCurso->avance($examenId);
        $incidencias = $this->enCurso->incidencias($examenId);
        $compartidas = $this->consulta->aulasCompartidas(
            $resumen->fecha,
            $resumen->hora,
            $resumen->duracion,
            $examenId,
        );
        $aulasDetalle = array_values(array_filter(
            $detalle->aulas,
            static fn (AulaDetalleData $aula): bool => $esDocente || in_array($aula->aulaId, $mias, true),
        ));

        if (! $esDocente && $aulasDetalle === []) {
            throw new ExamenAjenoException('No atiende aulas de este examen.');
        }

        $idsVisibles = array_map(
            static fn (AulaDetalleData $aula): int => $aula->aulaId,
            $aulasDetalle,
        );
        $incidencias = array_values(array_filter(
            $incidencias,
            static fn (IncidenciaEnCursoData $incidencia): bool => $esDocente
                || in_array($incidencia->aulaId, $idsVisibles, true),
        ));
        $porAtender = $this->sinAtenderPorAula($incidencias);
        $avanceVisible = $esDocente
            ? $avance
            : $this->avanceDeAulasVisibles($avance, $idsVisibles);

        return new TableroEnCursoData(
            examen: new ExamenEnCursoData(
                id: $resumen->id,
                fecha: $resumen->fecha,
                hora: $resumen->hora,
                duracion: $resumen->duracion,
                tipo: $resumen->tipo,
                tipoTexto: $resumen->tipoTexto,
                asignatura: $resumen->asignaturaNombre,
                abierto: $this->ventana->estaAbierta($examenId),
            ),
            horaServidor: $this->consulta->horaServidor(),
            avance: $avanceVisible,
            aulas: array_map(
                static fn (AulaDetalleData $aula): AulaEnCursoData => new AulaEnCursoData(
                    aulaId: $aula->aulaId,
                    nombre: $aula->nombre,
                    ubicacion: $aula->ubicacion,
                    edificioId: $aula->edificioId,
                    aCargo: $aula->aCargo,
                    apoyo: $aula->apoyo,
                    avance: $avance->deAula($aula->aulaId),
                    incidenciasSinAtender: $porAtender[$aula->aulaId] ?? 0,
                    compartida: $compartidas[$aula->aulaId] ?? [],
                    codigoAcceso: $aula->codigo !== null,
                    aMiCargo: in_array($aula->aulaId, $mias, true),
                ),
                $aulasDetalle,
            ),
            incidencias: $incidencias,
        );
    }

    /**
     * @param  list<IncidenciaEnCursoData>  $incidencias
     * @return array<int, int>
     */
    private function sinAtenderPorAula(array $incidencias): array
    {
        $conteo = [];

        foreach ($incidencias as $incidencia) {
            if (! $incidencia->atendida()) {
                $conteo[$incidencia->aulaId] = ($conteo[$incidencia->aulaId] ?? 0) + 1;
            }
        }

        return $conteo;
    }

    /**
     * @param  list<int>  $aulaIds
     */
    private function avanceDeAulasVisibles(AvanceEnCursoData $avance, array $aulaIds): AvanceEnCursoData
    {
        $aulas = [];
        $ingresaron = 0;
        $faltan = 0;
        $ultimosRegistros = [];

        foreach ($aulaIds as $aulaId) {
            $avanceAula = $avance->deAula($aulaId);
            $aulas[$aulaId] = $avanceAula;
            $ingresaron += $avanceAula->ingresados;
            $faltan += $avanceAula->faltan;

            if ($avanceAula->ultimoRegistro !== null) {
                $ultimosRegistros[] = $avanceAula->ultimoRegistro;
            }
        }

        rsort($ultimosRegistros, SORT_STRING);

        return new AvanceEnCursoData(
            ingresaron: $ingresaron,
            faltan: $faltan,
            ultimoRegistro: $ultimosRegistros[0] ?? null,
            aulas: $aulas,
        );
    }
}
