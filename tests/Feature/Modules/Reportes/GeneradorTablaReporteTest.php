<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Reportes\Application\DTOs\TablaReporte;
use App\Modules\Reportes\Infrastructure\Export\GeneradorTablaReporteDompdfPhpSpreadsheet;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

final class GeneradorTablaReporteTest extends TestCase
{
    private function tabla(): TablaReporte
    {
        return new TablaReporte(
            clave: 'asistencia',
            titulo: 'Reporte de asistencia',
            filtros: ['Facultad: FCyT'],
            encabezados: ['Código SIS', 'Estudiante'],
            filas: [
                ['00123456', '=HYPERLINK("https://example.test","X")'],
                ['00000002', '<script>alert(1)</script>'],
            ],
            totales: ['Total: 2'],
            emitidoEn: '2026-10-30 15:30',
        );
    }

    public function test_genera_pdf_valido_con_datos_reales_de_la_tabla(): void
    {
        $archivo = (new GeneradorTablaReporteDompdfPhpSpreadsheet)
            ->generar($this->tabla(), 'pdf');

        $this->assertSame('application/pdf', $archivo->tipoContenido);
        $this->assertStringEndsWith('.pdf', $archivo->nombre);
        $this->assertStringStartsWith('%PDF-', $archivo->contenido);
        $this->assertGreaterThan(1000, strlen($archivo->contenido));
    }

    public function test_xlsx_conserva_codigos_y_neutraliza_formulas(): void
    {
        $archivo = (new GeneradorTablaReporteDompdfPhpSpreadsheet)
            ->generar($this->tabla(), 'xlsx');

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $archivo->tipoContenido,
        );
        $this->assertStringEndsWith('.xlsx', $archivo->nombre);
        $this->assertStringStartsWith('PK', $archivo->contenido);

        $ruta = tempnam(sys_get_temp_dir(), 'tis-xlsx-');

        if ($ruta === false) {
            $this->fail('No se pudo crear un archivo temporal.');
        }

        try {
            file_put_contents($ruta, $archivo->contenido);
            $libro = IOFactory::load($ruta);

            try {
                $hoja = $libro->getActiveSheet();

                $this->assertSame('00123456', $hoja->getCell('A7')->getValue());
                $this->assertSame(
                    '=HYPERLINK("https://example.test","X")',
                    $hoja->getCell('B7')->getValue(),
                );
                $this->assertSame(
                    DataType::TYPE_STRING,
                    $hoja->getCell('B7')->getDataType(),
                );
                $this->assertSame(
                    '<script>alert(1)</script>',
                    $hoja->getCell('B8')->getValue(),
                );
            } finally {
                $libro->disconnectWorksheets();
            }
        } finally {
            unlink($ruta);
        }
    }

    public function test_rechaza_exportacion_que_supera_limite_sin_generar_archivo(): void
    {
        $tabla = new TablaReporte(
            clave: 'volumen',
            titulo: 'Reporte voluminoso',
            filtros: [],
            encabezados: ['Código'],
            filas: array_fill(
                0,
                GeneradorTablaReporteDompdfPhpSpreadsheet::MAX_FILAS_EXPORTACION + 1,
                ['001']
            ),
            totales: [],
            emitidoEn: '2026-10-30 15:30',
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('supera el límite');

        (new GeneradorTablaReporteDompdfPhpSpreadsheet)
            ->generar($tabla, 'xlsx');
    }

    public function test_rechaza_formatos_desconocidos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new GeneradorTablaReporteDompdfPhpSpreadsheet)
            ->generar($this->tabla(), 'csv');
    }

    public function test_rechaza_columnas_inconsistentes(): void
    {
        $tabla = new TablaReporte(
            clave: 'invalido',
            titulo: 'Tabla inválida',
            filtros: [],
            encabezados: ['Código', 'Nombre'],
            filas: [['001']],
            totales: [],
            emitidoEn: '2026-10-30 15:30',
        );

        $this->expectException(InvalidArgumentException::class);

        (new GeneradorTablaReporteDompdfPhpSpreadsheet)
            ->generar($tabla, 'xlsx');
    }
}
