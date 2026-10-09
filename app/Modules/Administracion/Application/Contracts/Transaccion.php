<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use Closure;

/**
 * Deja que una accion haga varias escrituras ---y su asiento en la
 * bitacora--- como una sola: o quedan todas o ninguna.
 */
interface Transaccion
{
    /**
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function ejecutar(Closure $operacion): mixed;
}
