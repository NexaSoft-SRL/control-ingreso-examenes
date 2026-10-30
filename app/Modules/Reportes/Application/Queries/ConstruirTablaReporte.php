<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Queries;

use App\Modules\Reportes\Application\DTOs\AlcanceReporte;
use App\Modules\Reportes\Application\DTOs\TablaReporte;
use InvalidArgumentException;

final readonly class ConstruirTablaReporte
{
    public function __construct(
        private ConsultarReporte $consultar,
    ) {}

    public function execute(string $tipo, AlcanceReporte $alcance): TablaReporte
    {
        if (in_array($tipo, ['incidencias', 'asistencia', 'ausentes'], true)
            && $alcance->examenId === null) {
            throw new InvalidArgumentException('Debe seleccionar un examen.');
        }

        $filtros = [];

        if ($alcance->periodoId !== null) {
            $filtros[] = 'Período ID: '.$alcance->periodoId;
        }

        if ($alcance->facultad !== null) {
            $filtros[] = 'Facultad: '.$alcance->facultad;
        }

        if ($alcance->buscar !== null) {
            $filtros[] = 'Búsqueda: '.$alcance->buscar;
        }

        if ($alcance->desde !== null) {
            $filtros[] = 'Desde: '.$alcance->desde;
        }

        if ($alcance->hasta !== null) {
            $filtros[] = 'Hasta: '.$alcance->hasta;
        }

        if ($alcance->examenId !== null) {
            $filtros[] = 'Examen ID: '.$alcance->examenId;
        }

        $titulo = '';
        $encabezados = [];
        $filas = [];
        $totales = [];

        switch ($tipo) {
            case 'horarios':
                $reporte = $this->consultar->horarios($alcance);
                $titulo = 'Horarios de ingreso';
                $encabezados = ['Tramo horario', 'Ingresos'];

                foreach ($reporte->tramos as $tramo) {
                    $filas[] = [$tramo['tramo'], (string) $tramo['cantidad']];
                }

                $totales = ['Total de ingresos: '.$reporte->total];
                break;

            case 'registrados':
                $titulo = 'Habilitados y registrados';
                $encabezados = [
                    'Fecha', 'Examen', 'Facultad', 'Docente',
                    'Aula', 'Habilitados', 'Registrados',
                ];

                foreach ($this->consultar->registrados($alcance) as $fila) {
                    $filas[] = [
                        $fila->fecha, $fila->examen, $fila->facultad ?? '',
                        $fila->docente, $fila->aula ?? '',
                        (string) $fila->habilitados, (string) $fila->registrados,
                    ];
                }
                break;

            case 'intentos':
                $titulo = 'Intentos de ingreso no autorizados';
                $encabezados = [
                    'Fecha y hora', 'Código SIS', 'Estudiante',
                    'Aula', 'Examen intentado', 'Examen correspondiente', 'Causa',
                ];

                foreach ($this->consultar->intentos($alcance) as $fila) {
                    $filas[] = [
                        $fila->ocurridaEn, $fila->codigoUniversitario ?? '',
                        $fila->estudiante ?? '', $fila->aula ?? '',
                        $fila->examenIntentado ?? '',
                        $fila->examenCorrespondiente ?? '',
                        $fila->causa ?? '',
                    ];
                }
                break;

            case 'estudiantes':
                $titulo = 'Resumen por estudiante';
                $encabezados = [
                    'Código SIS', 'Estudiante', 'Carrera', 'Materias',
                    'Exámenes', 'Ingresos', 'Intentos', 'Expulsiones', 'Incidencias',
                ];

                foreach ($this->consultar->resumenPorEstudiante($alcance) as $fila) {
                    $filas[] = [
                        $fila->codigoUniversitario, $fila->nombre,
                        $fila->carrera ?? '', $fila->materias,
                        (string) $fila->examenes, (string) $fila->ingresos,
                        (string) $fila->intentos, (string) $fila->expulsiones,
                        (string) $fila->incidencias,
                    ];
                }
                break;

            case 'incidencias':
                $titulo = 'Incidencias del examen';
                $encabezados = [
                    'Hora', 'Aula', 'Código SIS', 'Estudiante',
                    'Tipo', 'Origen', 'Causa', 'Detalle',
                    'Registrado por', 'Con código de aula',
                ];

                foreach ($this->consultar->incidenciasDeExamen($alcance->examenId) as $fila) {
                    $filas[] = [
                        $fila->hora, $fila->aula ?? '',
                        $fila->codigoUniversitario ?? '',
                        $fila->estudiante ?? '', $fila->tipo,
                        $fila->origen, $fila->causa ?? '', $fila->detalle,
                        $fila->registradoPor ?? '',
                        $fila->conCodigoDeAula ? 'Sí' : 'No',
                    ];
                }
                break;

            case 'asistencia':
                $titulo = 'Asistencia al examen';
                $encabezados = [
                    'Código SIS', 'Estudiante', 'Grupo', 'Aula',
                    'Hora', 'Medio', 'Expulsado', 'Excepción',
                ];

                foreach ($this->consultar->asistencia($alcance->examenId) as $fila) {
                    $filas[] = [
                        $fila->codigoUniversitario, $fila->nombre,
                        $fila->grupo ?? '', $fila->aula ?? '',
                        $fila->hora, $fila->medio,
                        $fila->expulsado ? 'Sí' : 'No',
                        $fila->excepcion ? 'Sí' : 'No',
                    ];
                }
                break;

            case 'ausentes':
                $titulo = 'Estudiantes ausentes';
                $encabezados = [
                    'Código SIS', 'Estudiante', 'Grupo', 'Carrera', 'Aula',
                ];

                foreach ($this->consultar->ausentes($alcance->examenId) as $fila) {
                    $filas[] = [
                        $fila->codigoUniversitario, $fila->nombre,
                        $fila->grupo ?? '', $fila->carrera ?? '',
                        $fila->aula ?? '',
                    ];
                }
                break;

            default:
                throw new InvalidArgumentException('Tipo de reporte no válido.');
        }

        return new TablaReporte(
            clave: $tipo,
            titulo: $titulo,
            filtros: $filtros,
            encabezados: $encabezados,
            filas: $filas,
            totales: $totales,
            emitidoEn: now()->format('Y-m-d H:i'),
        );
    }
}
