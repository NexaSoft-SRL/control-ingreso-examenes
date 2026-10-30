<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Export;

use App\Modules\Reportes\Application\Contracts\GeneradorArchivoBitacora;
use App\Modules\Reportes\Application\DTOs\ArchivoReporte;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class GeneradorArchivoBitacoraDompdfPhpSpreadsheet implements GeneradorArchivoBitacora
{
    public function generar(array $examen, array $eventos, string $formato): ArchivoReporte
    {
        $nombre = sprintf('bitácora %s.%s', $examen['fecha'], $formato);
        $encabezados = ['Fecha', 'Hora', 'Suceso', 'Estudiante', 'Aula', 'Detalle', 'Actor', 'Examen'];
        $filas = array_map(static fn (array $evento): array => [
            substr($evento['ocurrida_en'], 0, 10),
            substr($evento['ocurrida_en'], 11, 5),
            $evento['suceso'],
            $evento['codigo'] === null
                ? $evento['estudiante']
                : $evento['estudiante'].' · '.$evento['codigo'],
            $evento['aula'] ?? 'Sin aula',
            $evento['detalle'],
            $evento['actor'],
            $examen['tipo'].' · '.$examen['asignatura'].' · '.$examen['fecha'],
        ], $eventos);
        $metadatos = [
            'examen' => $examen['tipo'].' · '.$examen['asignatura'],
            'fecha' => $examen['fecha'],
            'total' => count($eventos),
            'emitido' => now()->format('Y-m-d H:i'),
        ];

        return $formato === 'pdf'
            ? $this->pdf($nombre, $encabezados, $filas, $metadatos)
            : $this->xlsx($nombre, $encabezados, $filas, $metadatos);
    }

    /**
     * @param  list<string>  $encabezados
     * @param  list<list<string>>  $filas
     * @param  array{examen: string, fecha: string, total: int, emitido: string}  $metadatos
     */
    private function xlsx(
        string $nombre,
        array $encabezados,
        array $filas,
        array $metadatos,
    ): ArchivoReporte {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Bitácora');
        $hoja->setCellValue('A1', 'Bitácora del examen');
        $hoja->setCellValue('A2', $metadatos['examen']);
        $hoja->setCellValue('A3', sprintf(
            'Fecha del examen: %s · Emitido: %s · Total: %d evento(s)',
            $metadatos['fecha'],
            $metadatos['emitido'],
            $metadatos['total'],
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
            $hoja->setCellValue('A6', 'Sin eventos.');
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
     * @param  array{examen: string, fecha: string, total: int, emitido: string}  $metadatos
     */
    private function pdf(
        string $nombre,
        array $encabezados,
        array $filas,
        array $metadatos,
    ): ArchivoReporte {
        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view('reportes.bitacora-pdf', [
            'encabezados' => $encabezados,
            'filas' => $filas,
            'metadatos' => $metadatos,
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
