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
     * La nota de cada encabezado: que va y con que forma. Se lee al pasar
     * sobre la celda, sin agregar filas que la carga tomaria por datos.
     */
    private const NOTAS = [
        'codigo_universitario' => 'Código SIS: 5 a 10 dígitos. Ej.: 202104821',
        'documento_identidad' => 'C.I. con su complemento si tiene. Ej.: 7928194 o 5003748-1A',
        'nombres' => 'Solo letras. Ej.: Kevin René',
        'apellidos' => 'Solo letras. Ej.: Alvarado Claros',
        'codigo_asignatura' => 'Código de la asignatura en la oferta. Ej.: 2008019',
        'grupo' => 'Código del grupo, como en la oferta. Ej.: 1 o A1',
        'carrera' => 'Opcional: código o nombre de la carrera. Ej.: 411702',
    ];

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

            if (isset(self::NOTAS[$encabezado])) {
                $nota = $hoja->getComment([$columna, 1]);
                $nota->getText()->createText(self::NOTAS[$encabezado]);
                $nota->setWidth('220pt');
            }
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
