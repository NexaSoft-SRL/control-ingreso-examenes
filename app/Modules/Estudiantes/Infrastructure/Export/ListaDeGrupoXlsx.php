<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Export;

use App\Modules\Estudiantes\Application\Contracts\GeneradorListaDeGrupo;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * La lista de inscritos de un grupo: una hoja con la fila de encabezados y
 * una fila por estudiante. Todo va como texto, para que la hoja de calculo
 * no se coma los ceros de un codigo o de un documento ni ejecute como
 * formula un valor que empiece con «=».
 */
final class ListaDeGrupoXlsx implements GeneradorListaDeGrupo
{
    /**
     * @param  list<string>  $encabezados
     * @param  list<list<string>>  $filas
     */
    public function generar(array $encabezados, array $filas): string
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Estudiantes');

        foreach ([$encabezados, ...$filas] as $indice => $fila) {
            foreach ($fila as $columna => $valor) {
                $hoja->setCellValueExplicit([$columna + 1, $indice + 1], $valor, DataType::TYPE_STRING);
            }
        }

        foreach (array_keys($encabezados) as $columna) {
            $hoja->getColumnDimensionByColumn($columna + 1)->setAutoSize(true);
        }

        $hoja->getStyle([1, 1, max(1, count($encabezados)), 1])->getFont()->setBold(true);
        $hoja->freezePane('A2');

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
