<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Export;

use App\Modules\Reportes\Application\Contracts\GeneradorArchivoReporte;
use App\Modules\Reportes\Application\DTOs\ArchivoReporte;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class GeneradorArchivoReporteDompdfPhpSpreadsheet implements GeneradorArchivoReporte
{
    public function generar(array $reporte, string $tipo, string $formato): ArchivoReporte
    {
        [$titulo, $encabezados, $filas] = $this->contenido($reporte['filas'], $tipo);
        $metadatos = [
            'asignatura' => $reporte['asignatura'],
            'periodo' => $reporte['periodo'],
            'tipo' => $reporte['tipo'],
            'fecha' => $reporte['fecha'],
            'hora' => $reporte['hora'],
        ];
        $etiquetaArchivo = match ($tipo) {
            'asistencia' => 'asistencia',
            'ausentes' => 'estudiantes ausentes',
            default => 'incidencias',
        };
        $nombre = sprintf('%s %s.%s', $etiquetaArchivo, $reporte['fecha'], $formato);

        return $formato === 'pdf'
            ? $this->pdf($nombre, $titulo, $encabezados, $filas, $metadatos)
            : $this->xlsx($nombre, $titulo, $encabezados, $filas, $metadatos);
    }

    /**
     * @param  array{
     *     asistencia: list<array{codigo: string, estudiante: string, grupo: string, aula: string, hora: string|null, medio: string, situacion: string}>,
     *     ausentes: list<array{apellidos: string, nombres: string, codigo: string, grupo: string, carrera: string, aula: string}>,
     *     incidencias: list<array{hora: string, aula: string, codigo: string|null, estudiante: string, tipo: string, detalle: string, registrado_por: string}>
     * }  $filasReporte
     * @return array{string, list<string>, list<list<string>>}
     */
    private function contenido(array $filasReporte, string $tipo): array
    {
        $celdas = [];

        if ($tipo === 'asistencia') {
            $encabezados = ['Código SIS', 'Estudiante', 'Grupo', 'Aula', 'Hora de ingreso', 'Medio', 'Situación'];
            $titulo = 'Reporte de asistencia';
            foreach ($filasReporte['asistencia'] as $fila) {
                $celdas[] = [
                    $fila['codigo'], $fila['estudiante'], $fila['grupo'], $fila['aula'],
                    $fila['hora'] ?? '', $fila['medio'], $fila['situacion'],
                ];
            }
        } elseif ($tipo === 'ausentes') {
            $encabezados = ['Apellidos', 'Nombres', 'Código SIS', 'Grupo', 'Carrera', 'Aula asignada'];
            $titulo = 'Reporte de estudiantes ausentes';
            foreach ($filasReporte['ausentes'] as $fila) {
                $celdas[] = [
                    $fila['apellidos'], $fila['nombres'], $fila['codigo'], $fila['grupo'],
                    $fila['carrera'], $fila['aula'],
                ];
            }
        } else {
            $encabezados = ['Hora', 'Aula', 'Código SIS', 'Estudiante', 'Tipo', 'Detalle', 'Registrado por'];
            $titulo = 'Reporte de incidencias';
            foreach ($filasReporte['incidencias'] as $fila) {
                $celdas[] = [
                    $fila['hora'], $fila['aula'], $fila['codigo'] ?? '', $fila['estudiante'],
                    $fila['tipo'], $fila['detalle'], $fila['registrado_por'],
                ];
            }
        }

        return [$titulo, $encabezados, $celdas];
    }

    /**
     * @param  list<string>  $encabezados
     * @param  list<list<string>>  $filas
     * @param  array{asignatura: array{nombre: string}, periodo: string, tipo: string, fecha: string, hora: string}  $reporte
     */
    private function xlsx(
        string $nombre,
        string $titulo,
        array $encabezados,
        array $filas,
        array $reporte,
    ): ArchivoReporte {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Reporte');
        $hoja->setCellValue('A1', $titulo);
        $hoja->setCellValue('A2', 'Universidad Mayor de San Simón · Control de ingreso a exámenes');
        $hoja->setCellValue('A3', sprintf(
            '%s · %s · %s · %s · Emitido el %s',
            $reporte['asignatura']['nombre'],
            $reporte['tipo'],
            $reporte['fecha'],
            $reporte['hora'],
            now()->format('Y-m-d H:i'),
        ));

        foreach ([$encabezados, ...$filas] as $indice => $fila) {
            foreach ($fila as $columna => $valor) {
                $hoja->setCellValueExplicit(
                    [$columna + 1, $indice + 5],
                    $valor,
                    DataType::TYPE_STRING,
                );
            }
        }

        $ancho = count($encabezados);
        $hoja->getStyle([1, 1, $ancho, 1])->getFont()->setBold(true);
        $hoja->getStyle([1, 5, $ancho, 5])->getFont()->setBold(true);
        $hoja->freezePane('A6');

        foreach (range(1, $ancho) as $columna) {
            $hoja->getColumnDimensionByColumn($columna)->setAutoSize(true);
        }

        if ($filas === []) {
            $hoja->setCellValue('A6', 'Sin datos para estos filtros.');
        }

        ob_start();

        try {
            (new Xlsx($libro))->save('php://output');
            $contenido = (string) ob_get_contents();
        } finally {
            ob_end_clean();
            $libro->disconnectWorksheets();
        }

        return new ArchivoReporte(
            $nombre,
            $contenido,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
    }

    /**
     * @param  list<string>  $encabezados
     * @param  list<list<string>>  $filas
     * @param  array{asignatura: array{nombre: string}, periodo: string, tipo: string, fecha: string, hora: string}  $reporte
     */
    private function pdf(
        string $nombre,
        string $titulo,
        array $encabezados,
        array $filas,
        array $reporte,
    ): ArchivoReporte {
        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view('reportes.pdf', [
            'titulo' => $titulo,
            'encabezados' => $encabezados,
            'filas' => $filas,
            'reporte' => $reporte,
            'emitido' => now()->format('Y-m-d H:i'),
        ])->render(), 'UTF-8');
        $pdf->setPaper('letter', 'landscape');
        $pdf->render();
        $pdf->getCanvas()->page_text(
            720,
            570,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            'DejaVu Sans',
            8,
            [0.35, 0.39, 0.45],
        );

        return new ArchivoReporte($nombre, $pdf->output(), 'application/pdf');
    }
}
