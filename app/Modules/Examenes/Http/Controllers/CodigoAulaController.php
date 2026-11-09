<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Actions\RegenerarCodigoDeAula;
use App\Modules\Examenes\Application\DTOs\CodigoAulaData;
use App\Modules\Examenes\Application\Queries\ConsultarCodigoDeAula;
use App\Modules\Examenes\Domain\Exceptions\ExamenAjenoException;
use Illuminate\Http\JsonResponse;

final class CodigoAulaController
{
    use RespuestasDeExamen;

    public function show(int $examen, int $aula, ConsultarCodigoDeAula $consultar): JsonResponse
    {
        try {
            $codigo = $consultar->execute($examen, $aula, $this->usuarioId());
        } catch (ExamenAjenoException) {
            return $this->ajeno();
        }

        if ($codigo === null) {
            return $this->noEncontrado('Examen o aula no encontrados.');
        }

        return response()->json(['data' => $this->serializar($codigo)]);
    }

    public function regenerar(
        int $examen,
        int $aula,
        ConsultarCodigoDeAula $consultar,
        RegenerarCodigoDeAula $regenerar,
    ): JsonResponse {
        try {
            $anterior = $consultar->execute($examen, $aula, $this->usuarioId());
            $codigo = $regenerar->execute($examen, $aula, $this->usuarioId());
        } catch (ExamenAjenoException) {
            return $this->ajeno();
        }

        if ($codigo === null) {
            return $this->noEncontrado('Examen o aula no encontrados.');
        }

        return response()->json([
            'data' => $this->serializar($codigo),
            'message' => $anterior?->codigo === null ? 'Código generado.' : 'Código regenerado.',
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function serializar(CodigoAulaData $codigo): array
    {
        return [
            'codigo' => $codigo->codigo,
            'generado_en' => $codigo->generadoEn,
            'encargado' => $codigo->encargado,
            'examen' => $codigo->examen,
        ];
    }
}
