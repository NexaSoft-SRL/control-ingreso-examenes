<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Queries;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Reportes\Application\Contracts\BitacoraExamenGateway;
use App\Modules\Reportes\Application\DTOs\AlcanceReporte;
use App\Modules\Reportes\Application\DTOs\ControlDeAula;
use App\Modules\Reportes\Application\DTOs\ExamenReporteData;
use App\Modules\Reportes\Application\DTOs\FilaAsistencia;
use App\Modules\Reportes\Application\DTOs\FilaAusente;
use App\Modules\Reportes\Application\DTOs\FilaIncidencia;
use App\Modules\Reportes\Application\DTOs\FilaIntento;
use App\Modules\Reportes\Application\DTOs\FilaRegistrados;
use App\Modules\Reportes\Application\DTOs\SucesoExamen;
use App\Modules\Reportes\Application\DTOs\TablaReporte;
use App\Modules\Reportes\Domain\Enums\GravedadIncidencia;
use App\Modules\Reportes\Domain\Enums\MotivoIntento;
use App\Modules\Reportes\Domain\Exceptions\ExamenSinRendirException;
use App\Modules\Reportes\Domain\Exceptions\RecursoAjenoException;
use App\Modules\Reportes\Domain\Exceptions\ReporteSinDatosException;
use App\Modules\Reportes\Domain\Rules\FechaLegible;
use InvalidArgumentException;

/**
 * Convierte cualquiera de los siete reportes en una tabla de texto. Es lo
 * que permite que la exportacion sea una sola y no siete: el exportador
 * recibe encabezados y filas, no los DTO de cada reporte. La `clave` de la
 * tabla es el nombre del archivo, sin extension.
 */
final readonly class ArmarTabla
{
    /**
     * Reportes de toda la universidad: clave => [archivo, titulo].
     *
     * @var array<string, array{string, string}>
     */
    private const UNIVERSIDAD = [
        'horarios' => ['horarios_ingreso', 'Reporte de horarios de ingreso'],
        'registrados' => ['registrados_examen', 'Reporte de registrados por examen'],
        'intentos' => ['intentos_rechazados', 'Reporte de intentos rechazados'],
        'estudiantes' => ['resumen_estudiantes', 'Resumen por estudiante'],
    ];

    /**
     * Reportes de un examen del docente: clave => titulo.
     *
     * @var array<string, string>
     */
    private const EXAMEN = [
        'asistencia' => 'Reporte de asistencia',
        'ausentes' => 'Reporte de estudiantes ausentes',
        'incidencias' => 'Reporte de incidencias',
        'bitacora' => 'Bitácora del examen',
    ];

    /**
     * Las etiquetas de `incidencias.tipo`.
     *
     * @var array<string, string>
     */
    private const INCIDENCIAS = [
        'INTENTO_NO_AUTORIZADO' => 'Intento rechazado',
        'SUPLANTACION' => 'Suplantación de identidad',
        'MATERIAL_NO_PERMITIDO' => 'Material no permitido',
        'DISPOSITIVO_NO_PERMITIDO' => 'Dispositivo no permitido',
        'INGRESO_REPETIDO' => 'Ingreso repetido',
        'EXPULSION' => 'Expulsión',
        'CAMBIO_AULA' => 'Cambio de aula',
        'OTRA' => 'Otra',
    ];

    /**
     * @var array<string, string>
     */
    private const MEDIOS = [
        'QR' => 'Código QR',
        'CODIGO' => 'Código SIS',
        'DOCUMENTO' => 'C.I.',
    ];

    public function __construct(
        private ConsultarReporte $reportes,
        private AlcanceExamenGateway $alcanceExamen,
        private BitacoraExamenGateway $bitacora,
    ) {}

    /**
     * Deja un texto apto para un nombre de archivo.
     */
    public static function paraArchivo(string $texto): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $texto) ?? '';
    }

    /**
     * Un reporte de la universidad sobre los examenes del alcance.
     *
     * @throws ReporteSinDatosException si el alcance no tiene examenes
     */
    public function execute(string $clave, AlcanceReporte $alcance): TablaReporte
    {
        if (! array_key_exists($clave, self::UNIVERSIDAD)) {
            throw new InvalidArgumentException("Reporte desconocido: {$clave}.");
        }

        if (! $this->reportes->tieneExamenes($alcance)) {
            throw new ReporteSinDatosException('No hay exámenes en ese alcance.');
        }

        [$encabezados, $filas, $totales] = match ($clave) {
            'horarios' => $this->horarios($alcance),
            'registrados' => $this->registrados($alcance),
            'estudiantes' => $this->estudiantes($alcance),
            default => $this->intentos($alcance),
        };

        $nombres = $this->reportes->nombresDelAlcance($alcance);
        [$archivo, $titulo] = self::UNIVERSIDAD[$clave];

        $de = match (true) {
            $alcance->examenId !== null => 'examen'.$alcance->examenId,
            isset($nombres['facultad']) => self::paraArchivo(mb_strtolower($nombres['facultad'])),
            default => 'umss',
        };

        return new TablaReporte(
            clave: sprintf('%s_%s_%s', $archivo, $de, now()->format('Y-m-d')),
            titulo: $titulo,
            filtros: $this->filtros($alcance, $nombres),
            encabezados: $encabezados,
            filas: $filas,
            totales: $totales,
            emitidoEn: FechaLegible::instante(now()->format('Y-m-d H:i:s')),
        );
    }

    /**
     * Asistencia, ausentes o incidencias de un examen, para uno de sus
     * docentes.
     * Devuelve null si el examen no existe.
     *
     * @throws RecursoAjenoException si la cuenta no es docente del examen
     * @throws ExamenSinRendirException antes de la fecha del examen
     */
    public function deExamen(string $clave, int $examenId, int $usuarioId, bool $deLaUniversidad = false): ?TablaReporte
    {
        if (! array_key_exists($clave, self::EXAMEN)) {
            throw new InvalidArgumentException("Reporte desconocido: {$clave}.");
        }

        $examen = $this->reportes->examen($examenId);

        if ($examen === null) {
            return null;
        }

        if (! $deLaUniversidad && ! $this->alcanceExamen->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new RecursoAjenoException('Este examen no está a su cargo.');
        }

        if ($examen->fecha > now()->format('Y-m-d')) {
            throw new ExamenSinRendirException('El examen todavía no se rindió.');
        }

        [$encabezados, $filas, $totales] = match ($clave) {
            'asistencia' => $this->asistencia($examenId),
            'incidencias' => $this->incidencias($examenId),
            'bitacora' => $this->bitacora($examenId),
            default => $this->ausentes($examenId),
        };

        return new TablaReporte(
            clave: sprintf('%s_%s', $clave, $examen->fecha),
            titulo: self::EXAMEN[$clave],
            filtros: array_values(array_filter([
                $this->textoDeExamen($examen),
                $examen->grupos !== '' ? 'Grupos: '.$examen->grupos : null,
            ])),
            encabezados: $encabezados,
            filas: $filas,
            totales: $totales,
            emitidoEn: FechaLegible::instante(now()->format('Y-m-d H:i:s')),
        );
    }

    /**
     * Todo lo ocurrido en el examen, en orden.
     *
     * @return array{list<string>, list<list<string>>, list<string>}
     */
    private function bitacora(int $examenId): array
    {
        $sucesos = $this->bitacora->sucesos($examenId);

        return [
            ['Hora', 'Suceso', 'Gravedad', 'Estudiante', 'Aula', 'Detalle', 'Por'],
            array_map(
                static fn (SucesoExamen $s): array => [
                    FechaLegible::instante($s->instante),
                    $s->titulo,
                    $s->gravedad === null ? '' : GravedadIncidencia::from($s->gravedad)->etiqueta(),
                    trim(implode(' · ', array_filter([$s->estudiante, $s->codigo]))),
                    $s->aula ?? '',
                    $s->detalle,
                    $s->por ?? '',
                ],
                $sucesos,
            ),
            [
                sprintf('%d sucesos', count($sucesos)),
                ...array_map(
                    static fn (ControlDeAula $aula): string => sprintf(
                        'Aula %s · controló: %s · %d %s%s.',
                        $aula->aula,
                        $aula->quienes() === '' ? 'nadie asignado' : $aula->quienes(),
                        $aula->ingresos,
                        $aula->ingresos === 1 ? 'ingreso' : 'ingresos',
                        $aula->primerIngreso === null ? '' : sprintf(' (%s – %s)', $aula->primerIngreso, $aula->ultimoIngreso),
                    ),
                    $this->bitacora->controlDeAulas($examenId),
                ),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $nombres
     * @return list<string>
     */
    private function filtros(AlcanceReporte $alcance, array $nombres): array
    {
        if ($alcance->examenId !== null) {
            return [sprintf('Examen: %s', $nombres['examen'] ?? (string) $alcance->examenId)];
        }

        $filtros = [];

        if (isset($nombres['periodo'])) {
            $filtros[] = sprintf('Período: %s', $nombres['periodo']);
        }

        if (isset($nombres['facultad'])) {
            $filtros[] = sprintf('Facultad: %s', $nombres['facultad']);
        }

        if ($alcance->buscar !== null) {
            $filtros[] = sprintf('Búsqueda: %s', $alcance->buscar);
        }

        if ($alcance->desde !== null) {
            $filtros[] = 'Desde: '.FechaLegible::dia($alcance->desde);
        }

        if ($alcance->hasta !== null) {
            $filtros[] = 'Hasta: '.FechaLegible::dia($alcance->hasta);
        }

        return $filtros === []
            ? ['Sin filtros: todos los exámenes.']
            : $filtros;
    }

    private function textoDeExamen(ExamenReporteData $examen): string
    {
        return sprintf(
            'Examen: %s · %s (%s, %s)',
            $examen->asignatura,
            $examen->tipo,
            FechaLegible::dia($examen->fecha),
            $examen->hora,
        );
    }

    /**
     * @return array{list<string>, list<list<string>>, list<string>}
     */
    private function horarios(AlcanceReporte $alcance): array
    {
        $reporte = $this->reportes->horarios($alcance);

        // Cada tramo es de un dia: '2026-10-12 08:15'.
        $filas = array_map(
            static fn (array $tramo): array => [
                FechaLegible::dia($tramo['tramo']),
                substr($tramo['tramo'], 11, 5),
                (string) $tramo['cantidad'],
            ],
            $reporte->tramos,
        );

        return [
            ['Día', 'Tramo de 15 min', 'Ingresos'],
            $filas,
            [
                sprintf('Primer ingreso: %s.', $reporte->primerIngreso === null ? 'ninguno' : FechaLegible::instante($reporte->primerIngreso)),
                sprintf('Último ingreso: %s.', $reporte->ultimoIngreso === null ? 'ninguno' : FechaLegible::instante($reporte->ultimoIngreso)),
                sprintf('%d ingresos en total.', $reporte->total),
            ],
        ];
    }

    /**
     * @return array{list<string>, list<list<string>>, list<string>}
     */
    private function registrados(AlcanceReporte $alcance): array
    {
        $datos = $this->reportes->registrados($alcance);

        $filas = array_map(
            static fn (FilaRegistrados $fila): array => [
                FechaLegible::dia($fila->fecha),
                $fila->examen,
                $fila->facultad ?? '',
                $fila->docente,
                $fila->aula ?? 'Sin aula',
                (string) $fila->habilitados,
                (string) $fila->registrados,
            ],
            $datos,
        );

        $habilitados = 0;
        $registrados = 0;
        $examenes = [];

        foreach ($datos as $fila) {
            $habilitados += $fila->habilitados;
            $registrados += $fila->registrados;
            $examenes[$fila->examenId] = true;
        }

        return [
            ['Fecha', 'Examen', 'Facultad', 'Docente', 'Aula', 'Habilitados', 'Ingresaron'],
            $filas,
            [
                sprintf('%d exámenes.', count($examenes)),
                sprintf('%d habilitados.', $habilitados),
                sprintf('%d ingresos.', $registrados),
            ],
        ];
    }

    /**
     * @return array{list<string>, list<list<string>>, list<string>}
     */
    private function intentos(AlcanceReporte $alcance): array
    {
        $datos = $this->reportes->intentos($alcance);

        $filas = array_map(
            static fn (FilaIntento $fila): array => [
                FechaLegible::instante($fila->ocurridaEn),
                $fila->codigoUniversitario ?? '',
                $fila->estudiante ?? 'Sin identificar',
                $fila->aula ?? 'Sin aula',
                $fila->examenIntentado ?? 'Ninguno a esa hora',
                $fila->examenCorrespondiente ?? 'Ninguno a esa hora',
                MotivoIntento::tryFrom($fila->causa ?? '')?->etiqueta() ?? 'Sin motivo',
            ],
            $datos,
        );

        return [
            [
                'Hora', 'Código SIS', 'Estudiante', 'Aula',
                'Examen al que intentó ingresar', 'Examen que le correspondía', 'Motivo',
            ],
            $filas,
            [sprintf('%d intentos rechazados.', count($datos))],
        ];
    }

    /**
     * @return array{list<string>, list<list<string>>, list<string>}
     */
    private function estudiantes(AlcanceReporte $alcance): array
    {
        $datos = $this->reportes->resumenPorEstudiante($alcance);

        $ingresos = 0;
        $intentos = 0;
        $expulsiones = 0;
        $incidencias = 0;
        $filas = [];

        foreach ($datos as $fila) {
            $ingresos += $fila->ingresos;
            $intentos += $fila->intentos;
            $expulsiones += $fila->expulsiones;
            $incidencias += $fila->incidencias;

            $filas[] = [
                $fila->codigoUniversitario,
                $fila->nombre,
                $fila->carrera ?? '',
                $fila->materias,
                (string) $fila->examenes,
                (string) $fila->ingresos,
                (string) $fila->intentos,
                (string) $fila->expulsiones,
                (string) $fila->incidencias,
            ];
        }

        return [
            [
                'Código SIS', 'Estudiante', 'Carrera', 'Materias',
                'Exámenes', 'Ingresos', 'Intentos', 'Expulsiones', 'Incidencias',
            ],
            $filas,
            [
                sprintf('%d estudiantes.', count($datos)),
                sprintf('%d ingresos.', $ingresos),
                sprintf('%d intentos rechazados.', $intentos),
                sprintf('%d expulsiones.', $expulsiones),
                sprintf('%d incidencias reportadas.', $incidencias),
            ],
        ];
    }

    /**
     * @return array{list<string>, list<list<string>>, list<string>}
     */
    private function incidencias(int $examenId): array
    {
        $datos = $this->reportes->incidenciasDeExamen($examenId);

        $intentos = 0;
        $excepciones = 0;
        $expulsiones = 0;
        $reportadas = 0;
        $porGravedad = array_fill_keys(array_map(
            static fn (GravedadIncidencia $gravedad): string => $gravedad->value,
            GravedadIncidencia::cases(),
        ), 0);
        $filas = [];

        foreach ($datos as $fila) {
            match (true) {
                $fila->origen === 'SISTEMA' => $intentos++,
                $fila->tipo === 'EXPULSION' => $expulsiones++,
                $fila->tipo === 'CAMBIO_AULA' => $excepciones++,
                default => $reportadas++,
            };
            $gravedad = GravedadIncidencia::deTipo($fila->tipo);
            $porGravedad[$gravedad->value]++;

            $filas[] = [
                $fila->hora,
                $fila->aula ?? 'Sin aula',
                $fila->codigoUniversitario ?? '',
                $fila->estudiante ?? '',
                $this->tipoDeIncidencia($fila),
                $gravedad->etiqueta(),
                $fila->detalle,
                $this->quienRegistro($fila),
            ];
        }

        return [
            ['Hora', 'Aula', 'Código SIS', 'Estudiante', 'Tipo', 'Gravedad', 'Detalle', 'Registrado por'],
            $filas,
            [
                sprintf('%d registros.', count($datos)),
                sprintf('%d intentos rechazados.', $intentos),
                sprintf('%d situaciones excepcionales.', $excepciones),
                sprintf('%d expulsiones.', $expulsiones),
                sprintf('%d incidencias reportadas.', $reportadas),
                sprintf(
                    'Muy graves: %d · graves: %d · leves: %d.',
                    $porGravedad[GravedadIncidencia::MuyGrave->value],
                    $porGravedad[GravedadIncidencia::Grave->value],
                    $porGravedad[GravedadIncidencia::Leve->value],
                ),
            ],
        ];
    }

    /**
     * El rechazo de la puerta lleva su motivo: es lo que lo distingue del
     * intento que reporto una persona.
     */
    private function tipoDeIncidencia(FilaIncidencia $fila): string
    {
        $tipo = self::INCIDENCIAS[$fila->tipo] ?? $fila->tipo;
        $motivo = $fila->origen === 'SISTEMA'
            ? MotivoIntento::tryFrom($fila->causa ?? '')?->etiqueta()
            : null;

        return $motivo === null ? $tipo : sprintf('%s · %s', $tipo, $motivo);
    }

    private function quienRegistro(FilaIncidencia $fila): string
    {
        if ($fila->registradoPor === null) {
            return $fila->origen === 'SISTEMA' ? 'Sistema' : '';
        }

        return $fila->conCodigoDeAula
            ? $fila->registradoPor.' (código de aula)'
            : $fila->registradoPor;
    }

    /**
     * @return array{list<string>, list<list<string>>, list<string>}
     */
    private function asistencia(int $examenId): array
    {
        $datos = $this->reportes->asistencia($examenId);
        $expulsados = 0;

        $filas = [];

        foreach ($datos as $fila) {
            if ($fila->expulsado) {
                $expulsados++;
            }

            $filas[] = [
                $fila->codigoUniversitario,
                $fila->nombre,
                $fila->grupo ?? '',
                $fila->docente ?? '',
                $fila->aula ?? 'Sin aula',
                $fila->hora,
                self::MEDIOS[$fila->medio] ?? $fila->medio,
                $this->situacion($fila),
            ];
        }

        return [
            ['Código SIS', 'Estudiante', 'Grupo', 'Docente', 'Aula', 'Hora de ingreso', 'Medio', 'Situación'],
            $filas,
            [
                sprintf('%d estudiantes con ingreso registrado.', count($datos)),
                sprintf('%d expulsados.', $expulsados),
            ],
        ];
    }

    /**
     * La expulsion pesa mas que la excepcion: es lo ultimo que le paso.
     */
    private function situacion(FilaAsistencia $fila): string
    {
        return match (true) {
            $fila->expulsado => 'Expulsado',
            $fila->excepcion => 'Excepción',
            default => 'Rindió',
        };
    }

    /**
     * @return array{list<string>, list<list<string>>, list<string>}
     */
    private function ausentes(int $examenId): array
    {
        $datos = $this->reportes->ausentes($examenId);

        $filas = array_map(
            static fn (FilaAusente $fila): array => [
                $fila->codigoUniversitario,
                $fila->nombre,
                $fila->grupo ?? '',
                $fila->docente ?? '',
                $fila->carrera ?? '',
                $fila->aula ?? 'Sin aula',
            ],
            $datos,
        );

        return [
            ['Código SIS', 'Estudiante', 'Grupo', 'Docente', 'Carrera', 'Aula asignada'],
            $filas,
            [sprintf('%d habilitados que no se presentaron.', count($datos))],
        ];
    }
}
