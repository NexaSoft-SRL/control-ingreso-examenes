<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Contracts;

/**
 * Dibuja la lista de inscritos de un grupo como hoja de calculo.
 */
interface GeneradorListaDeGrupo
{
    /**
     * @param  list<string>  $encabezados
     * @param  list<list<string>>  $filas
     * @return string el contenido binario del `.xlsx`
     */
    public function generar(array $encabezados, array $filas): string;
}
