<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\Transaccion;
use Closure;
use Illuminate\Support\Facades\DB;

final class TransaccionBaseDeDatos implements Transaccion
{
    public function ejecutar(Closure $operacion): mixed
    {
        return DB::transaction($operacion, 3);
    }
}
