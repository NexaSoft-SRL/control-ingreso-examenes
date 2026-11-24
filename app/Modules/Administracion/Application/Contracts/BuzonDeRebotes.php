<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\ReboteData;

/**
 * El buzon del remitente, donde el servidor de correo deja los avisos de
 * los correos que no se pudieron entregar.
 */
interface BuzonDeRebotes
{
    /**
     * Los rebotes permanentes aun no procesados. Un fallo al leer el buzon
     * no es un error de la aplicacion: devuelve una lista vacia.
     *
     * @return list<ReboteData>
     */
    public function rebotes(): array;

    /**
     * Deja los avisos ya procesados fuera de la proxima lectura.
     *
     * @param  list<string>  $mensajeIds
     */
    public function archivar(array $mensajeIds): void;
}
