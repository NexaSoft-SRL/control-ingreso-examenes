<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Actions\ListarDocentes;
use App\Modules\Examenes\Domain\Models\Docente;
use Illuminate\Http\JsonResponse;

final class DocenteController
{
    public function index(
        ListarDocentes $listarDocentes,
    ): JsonResponse {
        $data = array_map(
            static fn (Docente $docente): array => [
                'id' => $docente->getKey(),
                'codigo_docente' => $docente->codigo_docente,
                'nombres' => $docente->nombres,
                'apellidos' => $docente->apellidos,
            ],
            $listarDocentes->execute(),
        );

        return response()->json([
            'data' => $data,
        ]);
    }
}
