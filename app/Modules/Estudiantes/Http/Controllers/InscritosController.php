<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Controllers;

use App\Modules\Estudiantes\Application\Queries\ArmarListaDeGrupo;
use App\Modules\Estudiantes\Application\Queries\ArmarPlantilla;
use App\Modules\Estudiantes\Application\Queries\ListarInscritosDeGrupo;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoSinListaException;
use App\Modules\Estudiantes\Http\Requests\ListaRequest;
use App\Modules\Estudiantes\Http\Requests\PlantillaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * La lista de inscritos de un grupo del docente (ruta 40), su descarga
 * como hoja de calculo y la plantilla del archivo de carga (ruta 39).
 */
final class InscritosController
{
    public function index(ListaRequest $request, int $grupo, ListarInscritosDeGrupo $listar): JsonResponse
    {
        try {
            $pagina = $listar->execute((int) Auth::id(), $grupo, $request->toData());
        } catch (GrupoAjenoException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'alcance' => true,
            ], Response::HTTP_FORBIDDEN);
        }

        if ($pagina === null) {
            return response()->json(['message' => 'Grupo no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => $pagina->filas,
            'meta' => $pagina->meta(),
        ]);
    }

    public function plantilla(PlantillaRequest $request, ArmarPlantilla $armar): Response
    {
        return $this->hojaDeCalculo(ArmarPlantilla::NOMBRE, $armar->execute($request->alcance()));
    }

    public function descarga(int $grupo, ArmarListaDeGrupo $armar): JsonResponse|Response
    {
        try {
            $archivo = $armar->execute((int) Auth::id(), $grupo);
        } catch (GrupoAjenoException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'alcance' => true,
            ], Response::HTTP_FORBIDDEN);
        } catch (GrupoSinListaException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'codigo' => 'SIN_LISTA',
            ], Response::HTTP_CONFLICT);
        }

        if ($archivo === null) {
            return response()->json(['message' => 'Grupo no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return $this->hojaDeCalculo($archivo->nombre, $archivo->contenido);
    }

    private function hojaDeCalculo(string $nombre, string $contenido): Response
    {
        return response($contenido, Response::HTTP_OK, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Content-Length' => (string) strlen($contenido),
        ]);
    }
}
