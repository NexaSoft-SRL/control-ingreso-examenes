<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Actions;

use App\Modules\Academico\Application\Contracts\DeteccionPeriodoGateway;
use App\Modules\Academico\Application\Contracts\FuenteOfertaGateway;
use App\Modules\Academico\Application\Contracts\FuentePensumGateway;
use App\Modules\Academico\Application\Contracts\ImportacionGateway;
use App\Modules\Academico\Application\Contracts\OfertaGateway;
use App\Modules\Academico\Application\DTOs\OfertaFacultadData;
use App\Modules\Academico\Application\DTOs\ResumenImportacionData;
use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;
use App\Modules\Academico\Domain\Rules\CodigoPeriodo;
use App\Modules\Academico\Domain\Rules\NombresDeOferta;
use Throwable;

/**
 * Importa la oferta de una facultad: carreras, asignaturas, plan, docentes,
 * aulas, grupos y horarios, todo en una transaccion y por clave natural
 * (reimportar no duplica). Si algo falla no queda nada a medias y la
 * importacion queda registrada como fallida, con el motivo.
 */
final readonly class ImportarOferta
{
    public function __construct(
        private FuenteOfertaGateway $fuente,
        private FuentePensumGateway $pensum,
        private ImportacionGateway $importaciones,
        private OfertaGateway $oferta,
        private DeteccionPeriodoGateway $periodos,
        private ImportarUbicaciones $ubicaciones,
    ) {}

    /**
     * @param  string  $facultad  la clave (`fcyt`, `fce`, `fhce`, `fach`)
     *
     * @throws FuenteNoDisponibleException si la facultad no esta en el catalogo
     */
    public function execute(string $facultad, ?int $usuarioId = null): ResumenImportacionData
    {
        $facultad = mb_strtolower(trim($facultad));
        $datos = $this->importaciones->facultad($facultad);

        if ($datos === null) {
            throw new FuenteNoDisponibleException("La facultad «{$facultad}» no está en el catálogo.");
        }

        $importacionId = $this->importaciones->iniciar($datos['id'], $usuarioId);

        try {
            return $this->importaciones->enTransaccion(
                fn (): ResumenImportacionData => $this->importar($datos, $importacionId, $usuarioId),
            );
        } catch (Throwable $error) {
            $mensaje = $error instanceof FuenteNoDisponibleException
                ? $error->getMessage()
                : 'La importación se interrumpió: '.$error->getMessage();

            $this->importaciones->fallar($importacionId, $mensaje);

            return new ResumenImportacionData(
                facultad: $facultad,
                sigla: $datos['sigla'],
                importada: false,
                error: $mensaje,
                importacionId: $importacionId,
            );
        }
    }

    /**
     * @param  array{id: int, clave: string, sigla: string, codigo_umss: string}  $facultad
     */
    private function importar(array $facultad, int $importacionId, ?int $usuarioId): ResumenImportacionData
    {
        $oferta = $this->fuente->leer($facultad['clave']);
        $partes = $oferta->periodoCodigo === null ? null : CodigoPeriodo::partes($oferta->periodoCodigo);

        if ($partes === null) {
            throw new FuenteNoDisponibleException(
                "El archivo {$facultad['clave']}.json no dice de qué período es la oferta."
            );
        }

        $this->ubicaciones->execute($facultad['clave']);

        [$carreraIds, $sinCodigo] = $this->guardarCarreras($facultad, $oferta);

        $periodoSemestral = null;
        $periodoAnual = null;

        // Lo que se junta del archivo antes de escribir.
        $asignaturas = [];
        $plan = [];
        $grupos = [];
        $docentes = [];
        $aulas = [];
        $descartadas = 0;

        foreach ($oferta->carreras as $posicion => $carrera) {
            if ($carrera->anual) {
                $periodoAnual ??= $this->periodos->asegurar(
                    CodigoPeriodo::anualDe($partes['anio']),
                    $partes['anio'],
                    0,
                    CodigoPeriodo::tipo(0),
                );
                $periodoId = $periodoAnual;
            } else {
                $periodoSemestral ??= $this->periodos->asegurar(
                    $partes['numero'].'/'.$partes['anio'],
                    $partes['anio'],
                    $partes['numero'],
                    CodigoPeriodo::tipo($partes['numero']),
                );
                $periodoId = $periodoSemestral;
            }

            foreach ($carrera->asignaturas as $asignatura) {
                $asignaturas[$asignatura->codigo] ??= NombresDeOferta::titulo($asignatura->nombre);

                $plan[] = [
                    'carrera' => $posicion,
                    'asignatura' => $asignatura->codigo,
                    'nivel' => $asignatura->nivel,
                ];

                foreach ($asignatura->grupos as $grupo) {
                    $clave = $asignatura->codigo.'|'.$grupo->codigo;

                    // La misma asignatura se repite en varias carreras con
                    // los mismos grupos: vale la primera aparicion.
                    if (isset($grupos[$clave])) {
                        continue;
                    }

                    $docente = null;

                    if (NombresDeOferta::esDocente($grupo->docente)) {
                        $docente = NombresDeOferta::normalizar((string) $grupo->docente);
                        $docentes[$docente] ??= NombresDeOferta::persona((string) $grupo->docente);
                    }

                    foreach ($grupo->sesiones as $sesion) {
                        if ($sesion->aula !== null) {
                            $aulas[$sesion->aula] = true;
                        }
                    }

                    $descartadas += $grupo->sesionesDescartadas;

                    $grupos[$clave] = [
                        'periodo_id' => $periodoId,
                        'asignatura' => $asignatura->codigo,
                        'codigo' => $grupo->codigo,
                        'docente' => $docente,
                        'sesiones' => $grupo->sesiones,
                    ];
                }
            }
        }

        $asignaturaIds = $this->oferta->guardarAsignaturas($asignaturas);
        $docenteIds = $this->oferta->guardarDocentes($docentes);
        $aulaIds = $this->oferta->guardarAulas($facultad['id'], array_map('strval', array_keys($aulas)));

        $filasPlan = [];

        foreach ($plan as $fila) {
            $filasPlan[] = [
                'carrera_id' => $carreraIds[$fila['carrera']],
                'asignatura_id' => $asignaturaIds[$fila['asignatura']],
                'nivel' => $fila['nivel'],
            ];
        }

        $this->oferta->guardarPlan($filasPlan);

        $grupos = array_values($grupos);
        $filasGrupos = [];
        $sinDocente = 0;

        foreach ($grupos as $grupo) {
            if ($grupo['docente'] === null) {
                $sinDocente++;
            }

            $filasGrupos[] = [
                'periodo_id' => $grupo['periodo_id'],
                'asignatura_id' => $asignaturaIds[$grupo['asignatura']],
                'codigo' => $grupo['codigo'],
                'docente_id' => $grupo['docente'] === null ? null : $docenteIds[$grupo['docente']],
            ];
        }

        $grupoIds = $this->oferta->guardarGrupos($facultad['id'], $filasGrupos);
        $horarios = [];

        foreach ($grupos as $posicion => $grupo) {
            foreach ($grupo['sesiones'] as $sesion) {
                $horarios[] = [
                    'grupo_id' => $grupoIds[$posicion],
                    'aula_id' => $sesion->aula === null ? null : $aulaIds[$sesion->aula],
                    'dia' => $sesion->dia,
                    'hora_inicio' => $sesion->horaInicio,
                    'hora_fin' => $sesion->horaFin,
                    'es_auxiliatura' => $sesion->esAuxiliatura,
                ];
            }
        }

        $this->oferta->reemplazarHorarios($grupoIds, $horarios);

        $periodoIds = array_values(array_filter(
            [$periodoSemestral, $periodoAnual],
            static fn (?int $id): bool => $id !== null,
        ));

        $eliminados = $this->oferta->eliminarGruposAusentes($facultad['id'], $periodoIds, $grupoIds);

        $resumen = new ResumenImportacionData(
            facultad: $facultad['clave'],
            sigla: $facultad['sigla'],
            importada: true,
            error: null,
            periodoCodigo: $oferta->periodoCodigo,
            fechaFuente: $oferta->fechaFuente,
            carreras: count($oferta->carreras),
            asignaturas: count($asignaturas),
            grupos: count($grupos),
            gruposSinDocente: $sinDocente,
            docentes: count($docentes),
            aulas: count($aulas),
            sesionesDescartadas: $descartadas,
            carrerasSinCodigo: $sinCodigo,
            gruposEliminados: $eliminados,
            importacionId: $importacionId,
        );

        $this->importaciones->completar($importacionId, $resumen, $usuarioId);

        return $resumen;
    }

    /**
     * La carrera que llega sin codigo se resuelve por su nombre contra el
     * pensum de la facultad; si tampoco esta ahi, queda sin enlazar y se
     * cuenta.
     *
     * @param  array{id: int, clave: string, sigla: string, codigo_umss: string}  $facultad
     * @return array{0: list<int>, 1: int}
     */
    private function guardarCarreras(array $facultad, OfertaFacultadData $oferta): array
    {
        $pensum = null;
        $sinCodigo = 0;
        $carreras = [];

        foreach ($oferta->carreras as $carrera) {
            $codigo = $carrera->codigo;

            if ($codigo === null) {
                $pensum ??= $this->pensum->carrerasDe($facultad['codigo_umss']);
                $codigo = $pensum[NombresDeOferta::normalizar($carrera->nombre)] ?? null;

                if ($codigo === null) {
                    $sinCodigo++;
                }
            }

            $carreras[] = [
                'codigo' => $codigo,
                'nombre' => NombresDeOferta::titulo($carrera->nombre),
                'anual' => $carrera->anual,
            ];
        }

        return [$this->oferta->guardarCarreras($facultad['id'], $facultad['sigla'], $carreras), $sinCodigo];
    }
}
