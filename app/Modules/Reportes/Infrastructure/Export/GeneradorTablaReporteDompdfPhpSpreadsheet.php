<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Export;

use App\Modules\Reportes\Application\Contracts\GeneradorTablaReporte;
use App\Modules\Reportes\Application\DTOs\ArchivoReporte;
use App\Modules\Reportes\Application\DTOs\TablaReporte;
use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;
use LogicException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class GeneradorTablaReporteDompdfPhpSpreadsheet implements GeneradorTablaReporte
{
    public function generar(TablaReporte $tabla, string $formato): ArchivoReporte
    {
        if (! in_array($formato, ['pdf', 'xlsx'], true)) {
            throw new InvalidArgumentException('Formato de exportación no válido.');
        }

        $ancho = count($tabla->encabezados);

        if ($ancho === 0) {
            throw new InvalidArgumentException('El reporte debe tener encabezados.');
        }

        foreach ($tabla->filas as $fila) {
            if (count($fila) !== $ancho) {
                throw new InvalidArgumentException('Las columnas del reporte no coinciden.');
            }
        }

        $nombre = sprintf(
            'reporte-%s-%s.%s',
            preg_replace('/[^a-z0-9_-]/', '-', strtolower($tabla->clave)) ?: 'general',
            now()->format('Ymd-His'),
            $formato,
        );

        return $formato === 'pdf'
            ? $this->pdf($tabla, $nombre)
            : $this->xlsx($tabla, $nombre);
    }

    private function pdf(TablaReporte $tabla, string $nombre): ArchivoReporte
    {
        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('isPhpEnabled', false);

        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(
            view('reportes.tabla-pdf', ['tabla' => $tabla])->render(),
            'UTF-8',
        );
        $pdf->setPaper('letter', 'landscape');
        $pdf->render();

        return new ArchivoReporte(
            nombre: $nombre,
            contenido: $pdf->output(),
            tipoContenido: 'application/pdf',
        );
    }

    private function xlsx(TablaReporte $tabla, string $nombre): ArchivoReporte
    {
        $libro = new Spreadsheet;

        try {
            $hoja = $libro->getActiveSheet();
            $hoja->setTitle('Reporte');
            $hoja->setCellValueExplicit('A1', $tabla->titulo, DataType::TYPE_STRING);
            $hoja->setCellValueExplicit(
                'A2',
                'Universidad Mayor de San Simón · Control de ingreso a exámenes',
                DataType::TYPE_STRING,
            );
            $hoja->setCellValueExplicit(
                'A3',
                'Emitido: '.$tabla->emitidoEn,
                DataType::TYPE_STRING,
            );

            $filaActual = 4;

            foreach ($tabla->filtros as $filtro) {
                $hoja->setCellValueExplicit(
                    [1, $filaActual++],
                    $filtro,
                    DataType::TYPE_STRING,
                );
            }

            $filaActual++;
            $filaEncabezados = $filaActual;

            foreach ($tabla->encabezados as $indice => $encabezado) {
                $hoja->setCellValueExplicit(
                    [$indice + 1, $filaActual],
                    $encabezado,
                    DataType::TYPE_STRING,
                );
            }

            $hoja->getStyle([1, $filaActual, count($tabla->encabezados), $filaActual])
                ->getFont()->setBold(true);

            $filaActual++;

            foreach ($tabla->filas as $fila) {
                foreach ($fila as $indice => $valor) {
                    $hoja->setCellValueExplicit(
                        [$indice + 1, $filaActual],
                        $valor,
                        DataType::TYPE_STRING,
                    );
                }

                $filaActual++;
            }

            if ($tabla->filas === []) {
                $hoja->setCellValueExplicit(
                    [1, $filaActual++],
                    'Sin datos para estos filtros.',
                    DataType::TYPE_STRING,
                );
            }

            $filaActual++;

            foreach ($tabla->totales as $total) {
                $hoja->setCellValueExplicit(
                    [1, $filaActual++],
                    $total,
                    DataType::TYPE_STRING,
                );
            }

            $hoja->getStyle('A1')->getFont()->setBold(true);
            $hoja->freezePane('A'.($filaEncabezados + 1));

            ob_start();

            try {
                (new Xlsx($libro))->save('php://output');
                $contenido = ob_get_contents();

                if (! is_string($contenido)) {
                    throw new LogicException('No se pudo recuperar el XLSX generado.');
                }
            } finally {
                ob_end_clean();
            }

            return new ArchivoReporte(
                nombre: $nombre,
                contenido: $contenido,
                tipoContenido: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
        } finally {
            $libro->disconnectWorksheets();
        }
    }
}
