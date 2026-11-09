<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Controllers;

use App\Modules\Estudiantes\Application\Actions\AgregarInscrito;
use App\Modules\Estudiantes\Application\Actions\EditarEstudiante;
use App\Modules\Estudiantes\Application\Actions\HabilitarCambioDeFoto;
use App\Modules\Estudiantes\Application\Actions\QuitarInscrito;
use App\Modules\Estudiantes\Application\DTOs\BajaDeInscritoData;
use App\Modules\Estudiantes\Application\DTOs\EdicionDeEstudianteData;
use App\Modules\Estudiantes\Application\DTOs\SolicitudAltaData;
use App\Modules\Estudiantes\Application\Queries\ArmarListaDeGrupo;
use App\Modules\Estudiantes\Application\Queries\ArmarPlantilla;
use App\Modules\Estudiantes\Application\Queries\ListarInscritosDeGrupo;
use App\Modules\Estudiantes\Domain\Enums\ResultadoAlta;
use App\Modules\Estudiantes\Domain\Exceptions\CambioDeFotoNoPermitidoException;
use App\Modules\Estudiantes\Domain\Exceptions\DocumentoEnUsoException;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoSinListaException;
use App\Modules\Estudiantes\Domain\Exceptions\InscritoConIngresoException;
use App\Modules\Estudiantes\Domain\Exceptions\NoInscritoException;
use App\Modules\Estudiantes\Domain\Exceptions\PeriodoCerradoException;
use App\Modules\Estudiantes\Domain\Exceptions\YaInscritoException;
use App\Modules\Estudiantes\Http\Requests\AgregarInscritoRequest;
use App\Modules\Estudiantes\Http\Requests\EditarEstudianteRequest;
use App\Modules\Estudiantes\Http\Requests\HabilitarFotoRequest;
use App\Modules\Estudiantes\Http\Requests\ListaRequest;
use App\Modules\Estudiantes\Http\Requests\PlantillaRequest;
use App\Modules\Estudiantes\Http\Requests\QuitarInscritoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * La lista de inscritos de un grupo del docente (ruta 40), su descarga
 * como hoja de calculo, la plantilla del archivo de carga (ruta 39) y la
 * edicion de la lista sin archivo: agregar a un estudiante suelto y quitar
 * a un inscrito con motivo.
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

    public function agregar(AgregarInscritoRequest $request, int $grupo, AgregarInscrito $agregar): JsonResponse
    {
        try {
            $resultado = $agregar->execute(new SolicitudAltaData(
                usuarioId: (int) Auth::id(),
                grupoId: $grupo,
                codigoUniversitario: $request->codigo(),
                documentoIdentidad: $request->documento(),
                nombres: $request->nombres(),
                apellidos: $request->apellidos(),
            ));
        } catch (GrupoAjenoException $excepcion) {
            return $this->ajeno($excepcion);
        } catch (PeriodoCerradoException $excepcion) {
            return $this->periodoCerrado($excepcion);
        } catch (YaInscritoException $excepcion) {
            return $this->rechazo('codigo', 'YA_INSCRITO', $excepcion->getMessage());
        } catch (DocumentoEnUsoException $excepcion) {
            return $this->rechazo('documento', 'DOCUMENTO_EN_USO', $excepcion->getMessage());
        }

        if ($resultado === null) {
            return response()->json(['message' => 'Grupo no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            ['message' => $resultado->mensaje(), 'resultado' => $resultado->value],
            $resultado === ResultadoAlta::Conflicto ? Response::HTTP_ACCEPTED : Response::HTTP_CREATED,
        );
    }

    public function editar(
        EditarEstudianteRequest $request,
        int $grupo,
        int $estudiante,
        EditarEstudiante $editar,
    ): JsonResponse {
        try {
            $editado = $editar->execute(new EdicionDeEstudianteData(
                usuarioId: (int) Auth::id(),
                estudianteId: $estudiante,
                documento: $request->documento(),
                nombres: $request->nombres(),
                apellidos: $request->apellidos(),
            ), $grupo);
        } catch (GrupoAjenoException $excepcion) {
            return $this->ajeno($excepcion);
        } catch (NoInscritoException $excepcion) {
            return response()->json(['message' => $excepcion->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (DocumentoEnUsoException $excepcion) {
            return $this->rechazo('documento', 'DOCUMENTO_EN_USO', $excepcion->getMessage());
        }

        if (! $editado) {
            return response()->json(['message' => 'Estudiante no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Estudiante actualizado.']);
    }

    public function quitar(QuitarInscritoRequest $request, int $grupo, int $estudiante, QuitarInscrito $quitar): JsonResponse
    {
        try {
            $quitado = $quitar->execute(new BajaDeInscritoData(
                usuarioId: (int) Auth::id(),
                grupoId: $grupo,
                estudianteId: $estudiante,
                motivo: $request->motivo(),
                detalle: $request->detalle(),
            ));
        } catch (GrupoAjenoException $excepcion) {
            return $this->ajeno($excepcion);
        } catch (PeriodoCerradoException $excepcion) {
            return $this->periodoCerrado($excepcion);
        } catch (NoInscritoException $excepcion) {
            return response()->json(['message' => $excepcion->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (InscritoConIngresoException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'codigo' => 'CON_INGRESO',
            ], Response::HTTP_CONFLICT);
        }

        if (! $quitado) {
            return response()->json(['message' => 'Grupo no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Estudiante quitado del grupo.']);
    }

    /**
     * El docente habilita a un inscrito para que suba o cambie su foto
     * hasta el dia que elige, o cambia ese dia si ya estaba habilitado.
     */
    public function habilitarFoto(int $grupo, int $estudiante, HabilitarFotoRequest $request, HabilitarCambioDeFoto $habilitar): JsonResponse
    {
        try {
            $hasta = $habilitar->execute((int) Auth::id(), $grupo, $estudiante, now()->toDateString(), $request->hasta());
        } catch (GrupoAjenoException $excepcion) {
            return $this->ajeno($excepcion);
        } catch (NoInscritoException $excepcion) {
            return response()->json(['message' => $excepcion->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (CambioDeFotoNoPermitidoException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'codigo' => $excepcion->codigo,
            ], Response::HTTP_CONFLICT);
        }

        if ($hasta === null) {
            return response()->json(['message' => 'Grupo no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['hasta' => $hasta, 'message' => 'Foto habilitada.']);
    }

    private function ajeno(GrupoAjenoException $excepcion): JsonResponse
    {
        return response()->json([
            'message' => $excepcion->getMessage(),
            'alcance' => true,
        ], Response::HTTP_FORBIDDEN);
    }

    private function periodoCerrado(PeriodoCerradoException $excepcion): JsonResponse
    {
        return response()->json([
            'message' => $excepcion->getMessage(),
            'codigo' => 'PERIODO_CERRADO',
        ], Response::HTTP_CONFLICT);
    }

    /**
     * Un rechazo que el formulario muestra bajo el campo: 422 con el codigo
     * del motivo.
     */
    private function rechazo(string $campo, string $codigo, string $mensaje): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'codigo' => $codigo,
            'errors' => [$campo => [$mensaje]],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
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
