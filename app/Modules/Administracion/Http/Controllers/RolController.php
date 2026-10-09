<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\CrearRol;
use App\Modules\Administracion\Application\Actions\EliminarRol;
use App\Modules\Administracion\Application\Actions\ModificarRol;
use App\Modules\Administracion\Application\DTOs\RolData;
use App\Modules\Administracion\Application\Queries\ListarRoles;
use App\Modules\Administracion\Domain\Exceptions\PermisoPropioException;
use App\Modules\Administracion\Domain\Exceptions\RolConCuentasException;
use App\Modules\Administracion\Domain\Exceptions\RolDeInicioException;
use App\Modules\Administracion\Http\Requests\GuardarRolRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Roles y matriz de permisos: los tres de inicio y los que crea la
 * administracion.
 */
final class RolController
{
    public function index(ListarRoles $listar): JsonResponse
    {
        $permisos = [];

        foreach ($listar->catalogo() as $clave => $pantalla) {
            $permisos[] = ['clave' => $clave, 'pantalla' => $pantalla];
        }

        return response()->json([
            'data' => array_map(
                fn (RolData $rol): array => $this->serializar($rol),
                $listar->execute(),
            ),
            'meta' => [
                'permisos' => $permisos,
            ],
        ]);
    }

    public function store(GuardarRolRequest $request, CrearRol $crear): JsonResponse
    {
        $rol = $crear->execute(
            $request->nombre() ?? '',
            $request->permisos(),
            $this->usuarioId(),
        );

        return response()->json([
            'data' => $this->serializar($rol),
            'message' => 'Rol creado.',
        ], Response::HTTP_CREATED);
    }

    public function update(int $rol, GuardarRolRequest $request, ModificarRol $modificar): JsonResponse
    {
        try {
            $modificado = $modificar->execute(
                $rol,
                $request->nombre(),
                $request->permisos(),
                $this->usuarioId(),
            );
        } catch (RolDeInicioException $excepcion) {
            return $this->campoRechazado('nombre', $excepcion->getMessage());
        } catch (PermisoPropioException $excepcion) {
            return $this->campoRechazado('permisos', $excepcion->getMessage());
        }

        if ($modificado === null) {
            return $this->noEncontrado();
        }

        return response()->json([
            'data' => $this->serializar($modificado),
            'message' => 'Cambios guardados.',
        ]);
    }

    public function destroy(int $rol, EliminarRol $eliminar): JsonResponse|Response
    {
        try {
            $eliminado = $eliminar->execute($rol, $this->usuarioId());
        } catch (RolDeInicioException $excepcion) {
            return $this->conflicto('ROL_DE_INICIO', $excepcion->getMessage());
        } catch (RolConCuentasException $excepcion) {
            return $this->conflicto('ROL_CON_CUENTAS', $excepcion->getMessage());
        }

        if (! $eliminado) {
            return $this->noEncontrado();
        }

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(RolData $rol): array
    {
        return [
            'id' => $rol->id,
            'nombre' => $rol->nombre,
            'es_sistema' => $rol->esSistema,
            'cuentas' => $rol->cuentas,
            'permisos' => $rol->permisos,
        ];
    }

    /**
     * Con la forma de un error de validacion: el formulario lo muestra en
     * el campo.
     */
    private function campoRechazado(string $campo, string $mensaje): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'errors' => [$campo => [$mensaje]],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function conflicto(string $codigo, string $mensaje): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'codigo' => $codigo,
        ], Response::HTTP_CONFLICT);
    }

    private function noEncontrado(): JsonResponse
    {
        return response()->json([
            'message' => 'Rol no encontrado.',
        ], Response::HTTP_NOT_FOUND);
    }

    private function usuarioId(): ?int
    {
        $id = Auth::id();

        return is_int($id) ? $id : null;
    }
}
