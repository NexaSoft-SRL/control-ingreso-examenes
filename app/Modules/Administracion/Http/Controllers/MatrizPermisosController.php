<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Queries\ConsultarMatrizPermisos;
use Illuminate\Http\JsonResponse;

final class MatrizPermisosController
{
    public function __invoke(ConsultarMatrizPermisos $consultar): JsonResponse
    {
        return response()->json($consultar->execute());
    }
}
