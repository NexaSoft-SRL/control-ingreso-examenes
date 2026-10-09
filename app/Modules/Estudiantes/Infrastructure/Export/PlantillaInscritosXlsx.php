<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Export;

use App\Modules\Estudiantes\Application\Contracts\GeneradorPlantilla;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * La plantilla de inscritos: una hoja con la fila de encabezados. Las
 * columnas van como texto para que la hoja de calculo no se coma los ceros
 * a la izquierda de un codigo o de un documento.
 */
final class PlantillaInscritosXlsx implements GeneradorPlantilla
{
    /**
     * @param  list<string>  $encabezados
     */
    public function generar(array $encabezados): string
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Inscritos');

        foreach ($encabezados as $indice => $encabezado) {
            $columna = $indice + 1;

            $hoja->setCellValue([$columna, 1], $encabezado);
            $hoja->getColumnDimensionByColumn($columna)->setWidth(24);
            $hoja->getStyle([$columna, 1, $columna, 2000])
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $hoja->getStyle([1, 1, max(1, count($encabezados)), 1])->getFont()->setBold(true);

        ob_start();

        try {
            (new Xlsx($libro))->save('php://output');
        } finally {
            $contenido = (string) ob_get_clean();
            $libro->disconnectWorksheets();
        }

        return $contenido;
    }
}
