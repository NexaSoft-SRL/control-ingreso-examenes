<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Contracts;

use App\Modules\Estudiantes\Domain\Exceptions\ArchivoRechazadoException;

/**
 * Lee el archivo de inscritos y lo entrega como filas de texto. El formato
 * del archivo (D-09) queda aislado detras de este contrato.
 */
interface LectorListaGateway
{
    /**
     * @param  string  $extension  `csv` o `xlsx`, la del nombre original
     * @return list<list<string>>
     *
     * @throws ArchivoRechazadoException si el archivo no es del tipo que dice o no se puede abrir
     */
    public function leer(string $rutaArchivo, string $extension): array;
}
