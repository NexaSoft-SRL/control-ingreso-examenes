<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Import;

use App\Modules\Estudiantes\Application\Contracts\LectorListaGateway;
use App\Modules\Estudiantes\Domain\Exceptions\ArchivoRechazadoException;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;

/**
 * Lee la lista de inscritos desde una hoja de calculo (.xlsx) o un archivo
 * de valores separados por comas (.csv). Es el unico lugar que conoce el
 * formato del archivo (D-09): si la lista de la WebSIS llega distinta, se
 * cambia aqui. Antes de leer comprueba el tipo real del archivo.
 */
final class LectorListaPhpSpreadsheet implements LectorListaGateway
{
    public function __construct(
        private readonly InspectorDeArchivo $inspector,
    ) {}

    /**
     * @return list<list<string>>
     */
    public function leer(string $rutaArchivo, string $extension): array
    {
        $codificacion = $this->inspector->inspeccionar($rutaArchivo, $extension);

        try {
            if (mb_strtolower($extension) === 'xlsx') {
                $lector = new Xlsx;
            } else {
                $lector = new Csv;
                $lector->setDelimiter($this->delimitador($rutaArchivo));
                $lector->setInputEncoding($codificacion ?? 'UTF-8');
            }

            $lector->setReadDataOnly(true);

            $hoja = $lector->load($rutaArchivo)->getActiveSheet();
            $celdas = $hoja->toArray(null, true, false, false);
        } catch (Throwable) {
            throw ArchivoRechazadoException::noCorresponde();
        }

        $filas = [];

        foreach ($celdas as $fila) {
            $valores = [];

            foreach ($fila as $celda) {
                $valores[] = $this->texto($celda);
            }

            $filas[] = $valores;
        }

        return $filas;
    }

    /**
     * Una hoja de calculo entrega los codigos como numeros: 202104821.0
     * tiene que leerse «202104821».
     */
    private function texto(mixed $celda): string
    {
        if (is_float($celda) && floor($celda) === $celda && abs($celda) < 1e15) {
            return (string) (int) $celda;
        }

        return is_scalar($celda) ? (string) $celda : '';
    }

    /**
     * Las planillas que exporta la universidad usan coma o punto y coma
     * segun la configuracion regional del equipo.
     */
    private function delimitador(string $rutaArchivo): string
    {
        $manejador = fopen($rutaArchivo, 'r');

        if ($manejador === false) {
            return ',';
        }

        $primeraLinea = (string) fgets($manejador);
        fclose($manejador);

        return substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',') ? ';' : ',';
    }
}
