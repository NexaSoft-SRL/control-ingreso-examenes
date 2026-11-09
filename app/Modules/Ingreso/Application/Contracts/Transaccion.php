<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use Closure;

/**
 * Deja que una accion asiente varias cosas a la vez: o todas o ninguna.
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
