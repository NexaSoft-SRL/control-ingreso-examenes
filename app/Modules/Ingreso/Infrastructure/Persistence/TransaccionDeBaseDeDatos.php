<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\Transaccion;
use Closure;
use Illuminate\Support\Facades\DB;

final class TransaccionDeBaseDeDatos implements Transaccion
{
    public function ejecutar(Closure $operacion): mixed
    {
        return DB::transaction($operacion);
    }
}
