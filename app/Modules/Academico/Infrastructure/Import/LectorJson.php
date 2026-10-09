<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Import;

use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;
use JsonException;

/**
 * Lectura de un archivo JSON de las fuentes, con errores que se pueden
 * mostrar tal cual a quien importa.
 */
final class LectorJson
{
    /**
     * @return array<mixed>
     *
     * @throws FuenteNoDisponibleException
     */
    public static function leer(string $ruta): array
    {
        $nombre = basename($ruta);

        if (! is_file($ruta) || ! is_readable($ruta)) {
            throw new FuenteNoDisponibleException("No se encontró el archivo {$nombre}.");
        }

        $contenido = file_get_contents($ruta);

        if ($contenido === false) {
            throw new FuenteNoDisponibleException("No se pudo leer el archivo {$nombre}.");
        }

        try {
            $datos = json_decode($contenido, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new FuenteNoDisponibleException("El archivo {$nombre} no es un JSON válido.");
        }

        if (! is_array($datos)) {
            throw new FuenteNoDisponibleException("El archivo {$nombre} no tiene la forma esperada.");
        }

        return $datos;
    }

    public static function rutaGenda(string $archivo): string
    {
        $carpeta = config('umss.ruta_genda');
        $carpeta = is_string($carpeta) ? $carpeta : database_path('umss/genda');

        return rtrim($carpeta, '/').'/'.$archivo;
    }
}
