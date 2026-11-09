<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Export;

use App\Modules\Reportes\Application\Contracts\ExportadorReporte;
use App\Modules\Reportes\Application\DTOs\TablaReporte;
use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use LogicException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * El dibujo del archivo es infraestructura: la capa de aplicacion entrega
 * una tabla de texto y no sabe si termina en PDF o en hoja de calculo.
 *
 * El PDF se dibuja fila por fila sobre el lienzo, sin pasar por HTML:
 * maquetar una tabla HTML de mas de unos cientos de filas agota la
 * memoria, y un reporte de toda la universidad tiene miles.
 */
final class ExportadorReporteArchivo implements ExportadorReporte
{
    // Hoja carta apaisada, en puntos.
    private const ALTO = 612.0;

    private const MARGEN = 36.0;

    private const ANCHO = 792.0 - 2 * self::MARGEN;

    private const LETRA = 8.0;

    private const LINEA = 10.5;

    private const RELLENO = 3.0;

    private const TINTA = [0.118, 0.161, 0.231];

    private const GRIS = [0.278, 0.333, 0.412];

    private const FONDO = [0.945, 0.961, 0.976];

    private const BORDE = [0.886, 0.910, 0.941];

    private const BORDE_TITULO = [0.796, 0.835, 0.882];

    private CPDF $lienzo;

    private string $normal = '';

    private string $negrita = '';

    private float $y = 0.0;

    /**
     * @var array<string, float>
     */
    private array $anchos = [];

    public function pdf(TablaReporte $tabla): string
    {
        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($opciones);
        $metricas = $dompdf->getFontMetrics();

        $this->lienzo = new CPDF('letter', 'landscape', $dompdf);
        $this->normal = $metricas->getFont('DejaVu Sans', 'normal') ?? '';
        $this->negrita = $metricas->getFont('DejaVu Sans', 'bold') ?? $this->normal;
        $this->anchos = [];
        $this->y = self::MARGEN;

        $this->encabezado($tabla);

        $columnas = $this->columnas($tabla);
        $this->filaDeTitulos($tabla, $columnas);

        foreach ($tabla->filas as $fila) {
            $this->fila($tabla, $columnas, $fila);
        }

        if ($tabla->filas === []) {
            $this->celda(self::MARGEN, self::ANCHO, ['Sin datos para estos filtros.'], $this->normal, self::BORDE);
            $this->y += self::LINEA + 2 * self::RELLENO;
        }

        $this->y += 6;

        foreach ($tabla->totales as $total) {
            $this->saltarSiNoCabe(self::LINEA + 4);
            $this->lienzo->text(self::MARGEN, $this->y, $total, $this->negrita, 9, self::TINTA);
            $this->y += self::LINEA + 4;
        }

        $this->lienzo->page_text(
            self::MARGEN + self::ANCHO - 70,
            self::ALTO - self::MARGEN + 8,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $this->normal,
            7,
            self::GRIS,
        );

        return $this->lienzo->output();
    }

    public function hojaDeCalculo(TablaReporte $tabla): string
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle(mb_substr($tabla->titulo, 0, 31));

        $linea = 1;
        $hoja->setCellValue([1, $linea++], $tabla->titulo);

        // El archivo conserva los filtros con que se lo pidio y la fecha de
        // emision: fuera del sistema, nadie mas se la puede preguntar.
        foreach ($tabla->filtros as $filtro) {
            $hoja->setCellValue([1, $linea++], $filtro);
        }

        $hoja->setCellValue([1, $linea++], 'Emitido el '.$tabla->emitidoEn);
        $linea++;

        $columna = 1;

        foreach ($tabla->encabezados as $encabezado) {
            $hoja->setCellValue([$columna, $linea], $encabezado);
            $hoja->getStyle([$columna, $linea])->getFont()->setBold(true);
            $hoja->getColumnDimensionByColumn($columna)->setAutoSize(true);
            $columna++;
        }

        $linea++;

        foreach ($tabla->filas as $fila) {
            $columna = 1;

            foreach ($fila as $celda) {
                // Como texto: un código universitario no es un número, y
                // Excel le comería los ceros de la izquierda.
                $hoja->setCellValueExplicit(
                    [$columna, $linea],
                    $celda,
                    DataType::TYPE_STRING,
                );
                $columna++;
            }

            $linea++;
        }

        $linea++;

        foreach ($tabla->totales as $total) {
            $hoja->setCellValue([1, $linea++], $total);
        }

        $escritor = new Xlsx($libro);

        ob_start();
        $escritor->save('php://output');
        $contenido = ob_get_clean();

        $libro->disconnectWorksheets();

        if (! is_string($contenido)) {
            throw new LogicException('La hoja de cálculo del reporte no se pudo generar.');
        }

        return $contenido;
    }

    /**
     * Titulo, filtros y fecha de emision: van una sola vez, en la primera
     * pagina.
     */
    private function encabezado(TablaReporte $tabla): void
    {
        $this->lienzo->text(self::MARGEN, $this->y, $tabla->titulo, $this->negrita, 14, self::TINTA);
        $this->y += 20;

        $lineas = [
            'Universidad Mayor de San Simón · Control de exámenes',
            ...$tabla->filtros,
            'Emitido el '.$tabla->emitidoEn,
        ];

        foreach ($lineas as $linea) {
            foreach ($this->partir($linea, self::ANCHO, $this->normal) as $renglon) {
                $this->lienzo->text(self::MARGEN, $this->y, $renglon, $this->normal, self::LETRA, self::GRIS);
                $this->y += self::LINEA;
            }
        }

        $this->y += 8;
    }

    /**
     * El ancho de cada columna: la que cabe entera se queda con lo suyo y
     * las largas se reparten el resto (ahi el texto baja de renglon). Si
     * sobra, todas crecen en proporcion hasta llenar la hoja.
     *
     * @return list<float>
     */
    private function columnas(TablaReporte $tabla): array
    {
        $naturales = [];

        foreach ($tabla->encabezados as $indice => $encabezado) {
            $naturales[$indice] = $this->ancho($encabezado, $this->negrita);
        }

        foreach ($tabla->filas as $fila) {
            foreach ($fila as $indice => $celda) {
                $naturales[$indice] = max($naturales[$indice] ?? 0.0, $this->ancho($celda, $this->normal));
            }
        }

        if ($naturales === []) {
            return [self::ANCHO];
        }

        ksort($naturales);
        $naturales = array_map(static fn (float $ancho): float => $ancho + 2 * self::RELLENO + 1, array_values($naturales));

        $anchos = $naturales;
        $pendientes = array_keys($naturales);
        $disponible = self::ANCHO;

        // Las columnas que caben en su parte igual quedan fijas; se repite
        // con las demas hasta que ninguna quepa.
        while ($pendientes !== []) {
            $parte = $disponible / count($pendientes);
            $caben = array_filter($pendientes, static fn (int $i): bool => $naturales[$i] <= $parte);

            if ($caben === []) {
                foreach ($pendientes as $i) {
                    $anchos[$i] = $parte;
                }

                break;
            }

            foreach ($caben as $i) {
                $disponible -= $naturales[$i];
            }

            $pendientes = array_values(array_diff($pendientes, $caben));
        }

        $factor = self::ANCHO / array_sum($anchos);

        return array_values($factor > 1
            ? array_map(static fn (float $ancho): float => $ancho * $factor, $anchos)
            : $anchos);
    }

    /**
     * @param  list<float>  $columnas
     */
    private function filaDeTitulos(TablaReporte $tabla, array $columnas): void
    {
        $celdas = [];
        $alto = 1;

        foreach ($tabla->encabezados as $indice => $encabezado) {
            $celdas[$indice] = $this->partir($encabezado, ($columnas[$indice] ?? self::ANCHO) - 2 * self::RELLENO, $this->negrita);
            $alto = max($alto, count($celdas[$indice]));
        }

        $alto = $alto * self::LINEA + 2 * self::RELLENO;
        $x = self::MARGEN;

        foreach ($celdas as $indice => $renglones) {
            $ancho = $columnas[$indice] ?? self::ANCHO;

            $this->lienzo->filled_rectangle($x, $this->y, $ancho, $alto, self::FONDO);
            $this->celda($x, $ancho, $renglones, $this->negrita, self::BORDE_TITULO, $alto);
            $x += $ancho;
        }

        $this->y += $alto;
    }

    /**
     * @param  list<float>  $columnas
     * @param  list<string>  $fila
     */
    private function fila(TablaReporte $tabla, array $columnas, array $fila): void
    {
        $celdas = [];
        $renglones = 1;

        foreach ($fila as $indice => $celda) {
            $celdas[$indice] = $this->partir($celda, ($columnas[$indice] ?? self::ANCHO) - 2 * self::RELLENO, $this->normal);
            $renglones = max($renglones, count($celdas[$indice]));
        }

        $alto = $renglones * self::LINEA + 2 * self::RELLENO;

        // En cada hoja nueva se repiten los titulos de las columnas.
        if ($this->y + $alto > self::ALTO - self::MARGEN) {
            $this->lienzo->new_page();
            $this->y = self::MARGEN;
            $this->filaDeTitulos($tabla, $columnas);
        }

        $x = self::MARGEN;

        foreach ($celdas as $indice => $texto) {
            $ancho = $columnas[$indice] ?? self::ANCHO;

            $this->celda($x, $ancho, $texto, $this->normal, self::BORDE, $alto);
            $x += $ancho;
        }

        $this->y += $alto;
    }

    /**
     * @param  list<string>  $renglones
     * @param  array{float, float, float}  $borde
     */
    private function celda(float $x, float $ancho, array $renglones, string $fuente, array $borde, ?float $alto = null): void
    {
        $alto ??= count($renglones) * self::LINEA + 2 * self::RELLENO;

        $this->lienzo->rectangle($x, $this->y, $ancho, $alto, $borde, 0.5);

        $y = $this->y + self::RELLENO;

        foreach ($renglones as $renglon) {
            $this->lienzo->text($x + self::RELLENO, $y, $renglon, $fuente, self::LETRA, self::TINTA);
            $y += self::LINEA;
        }
    }

    private function saltarSiNoCabe(float $alto): void
    {
        if ($this->y + $alto > self::ALTO - self::MARGEN) {
            $this->lienzo->new_page();
            $this->y = self::MARGEN;
        }
    }

    /**
     * Parte el texto en renglones que caben en el ancho; la palabra que no
     * cabe sola se corta por letras.
     *
     * @return list<string>
     */
    private function partir(string $texto, float $ancho, string $fuente): array
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));

        if ($texto === '' || $this->ancho($texto, $fuente) <= $ancho) {
            return [$texto];
        }

        $renglones = [];
        $actual = '';

        foreach (explode(' ', $texto) as $palabra) {
            $candidato = $actual === '' ? $palabra : $actual.' '.$palabra;

            if ($this->ancho($candidato, $fuente) <= $ancho) {
                $actual = $candidato;

                continue;
            }

            if ($actual !== '') {
                $renglones[] = $actual;
            }

            $actual = '';

            foreach (mb_str_split($palabra) as $letra) {
                if ($actual !== '' && $this->ancho($actual.$letra, $fuente) > $ancho) {
                    $renglones[] = $actual;
                    $actual = '';
                }

                $actual .= $letra;
            }
        }

        if ($actual !== '') {
            $renglones[] = $actual;
        }

        return $renglones;
    }

    /**
     * Ancho del texto en puntos, a la letra de la tabla. Se guarda: en un
     * reporte largo las mismas palabras se repiten miles de veces.
     */
    private function ancho(string $texto, string $fuente): float
    {
        $clave = $fuente.'|'.$texto;

        if (! isset($this->anchos[$clave])) {
            $this->anchos[$clave] = $this->lienzo->get_text_width($texto, $fuente, self::LETRA);
        }

        return $this->anchos[$clave];
    }
}
