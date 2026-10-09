<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Import;

use App\Modules\Estudiantes\Domain\Exceptions\ArchivoRechazadoException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;

/**
 * Comprueba que el archivo sea de verdad lo que dice su extension: una
 * imagen o un documento renombrados a `.csv` o `.xlsx` no se leen.
 */
final class InspectorDeArchivo
{
    private const FIRMA_ZIP = "PK\x03\x04";

    /**
     * La codificacion con la que hay que leer un `.csv` (`UTF-8` o
     * `ISO-8859-1`); null en un `.xlsx`.
     *
     * @throws ArchivoRechazadoException si el archivo no corresponde
     */
    public function inspeccionar(string $rutaArchivo, string $extension): ?string
    {
        if (! is_file($rutaArchivo) || ! is_readable($rutaArchivo)) {
            throw ArchivoRechazadoException::noCorresponde();
        }

        if (mb_strtolower($extension) === 'xlsx') {
            $this->hojaDeCalculo($rutaArchivo);

            return null;
        }

        return $this->texto($rutaArchivo);
    }

    /**
     * Un `.xlsx` es un zip con un libro adentro.
     */
    private function hojaDeCalculo(string $rutaArchivo): void
    {
        if (file_get_contents($rutaArchivo, false, null, 0, 4) !== self::FIRMA_ZIP) {
            throw ArchivoRechazadoException::noCorresponde();
        }

        try {
            $legible = (new Xlsx)->canRead($rutaArchivo);
        } catch (Throwable) {
            $legible = false;
        }

        if (! $legible) {
            throw ArchivoRechazadoException::noCorresponde();
        }
    }

    /**
     * Un `.csv` es texto: sin bytes nulos ni caracteres de control, en
     * UTF-8 o en ISO-8859-1 (como lo guardan las hojas de calculo viejas).
     */
    private function texto(string $rutaArchivo): string
    {
        $contenido = file_get_contents($rutaArchivo);

        if ($contenido === false) {
            throw ArchivoRechazadoException::noCorresponde();
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $contenido) === 1) {
            throw ArchivoRechazadoException::noCorresponde();
        }

        if (mb_check_encoding($contenido, 'UTF-8')) {
            return 'UTF-8';
        }

        // En ISO-8859-1 los bytes 0x80 a 0x9F son controles: un texto no
        // los trae (Windows-1252 si, pero solo para comillas y guiones
        // tipograficos; se aceptan esos).
        if (preg_match('/[\x81\x8D\x8F\x90\x9D]/', $contenido) === 1) {
            throw ArchivoRechazadoException::noCorresponde();
        }

        return 'ISO-8859-1';
    }
}
