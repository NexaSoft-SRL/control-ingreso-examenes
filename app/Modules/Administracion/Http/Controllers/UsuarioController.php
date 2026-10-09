<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\ActualizarCuentaUsuario;
use App\Modules\Administracion\Application\Actions\CrearCuentaUsuario;
use App\Modules\Administracion\Application\Actions\RestablecerContrasenaTemporal;
use App\Modules\Administracion\Application\Contracts\ConsultaUsuariosGateway;
use App\Modules\Administracion\Application\DTOs\UsuarioConRolData;
use App\Modules\Administracion\Application\Queries\ListarUsuarios;
use App\Modules\Administracion\Domain\Exceptions\BloqueoPropioException;
use App\Modules\Administracion\Http\Requests\GuardarUsuarioRequest;
use App\Modules\Administracion\Http\Requests\ListarUsuariosRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Cuentas del sistema: no hay registro publico, se crean aqui con un rol
 * existente, de inicio o creado.
 */
final class UsuarioController
{
    public function index(ListarUsuariosRequest $request, ListarUsuarios $listar): JsonResponse
    {
        $pagina = $listar->execute($request->toData());

        return response()->json([
            'data' => array_map(
                fn (UsuarioConRolData $cuenta): array => $this->serializar($cuenta),
                $pagina->filas,
            ),
            'meta' => [
                'total' => $pagina->total,
                'pagina' => $pagina->pagina,
                'por_pagina' => $pagina->porPagina,
                'cuentas' => $pagina->cuentas,
                'conteos' => $pagina->conteos,
            ],
        ]);
    }

    public function store(
        GuardarUsuarioRequest $request,
        CrearCuentaUsuario $crear,
        ConsultaUsuariosGateway $usuarios,
    ): JsonResponse {
        $creada = $crear->execute($request->toNuevaCuenta(), $this->usuarioId());
        $cuenta = $usuarios->buscar($creada->id);

        return response()->json([
            'data' => $cuenta === null ? null : $this->serializar($cuenta),
            // Se devuelve esta unica vez: despues nadie puede volver a verla.
            'contrasena_temporal' => $creada->contrasenaTemporal,
            'enviada_a' => $creada->enviadaA,
            'caduca_en' => $creada->caducaEn,
            'message' => 'Usuario creado.',
        ], Response::HTTP_CREATED);
    }

    public function update(
        int $usuario,
        GuardarUsuarioRequest $request,
        ActualizarCuentaUsuario $actualizar,
    ): JsonResponse {
        try {
            $resultado = $actualizar->execute(
                $request->toActualizacion($usuario),
                $this->usuarioId(),
            );
        } catch (BloqueoPropioException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($resultado === null) {
            return $this->noEncontrado();
        }

        return response()->json([
            'data' => $this->serializar($resultado->cuenta),
            'message' => match ($resultado->estadoCambiadoA) {
                true => 'Cuenta desbloqueada.',
                false => 'Cuenta bloqueada.',
                null => 'Cambios guardados.',
            },
        ]);
    }

    public function contrasenaTemporal(
        int $usuario,
        RestablecerContrasenaTemporal $restablecer,
    ): JsonResponse {
        $emitida = $restablecer->execute($usuario, $this->usuarioId());

        if ($emitida === null) {
            return $this->noEncontrado();
        }

        return response()->json([
            'contrasena_temporal' => $emitida->contrasenaTemporal,
            'enviada_a' => $emitida->enviadaA,
            'caduca_en' => $emitida->caducaEn,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(UsuarioConRolData $cuenta): array
    {
        return [
            'id' => $cuenta->id,
            'nombre' => $cuenta->nombre,
            'usuario' => $cuenta->usuario,
            'correo' => $cuenta->correo,
            'rol' => $cuenta->rol,
            'estado' => $cuenta->activo ? 'activo' : 'bloqueado',
        ];
    }

    private function noEncontrado(): JsonResponse
    {
        return response()->json([
            'message' => 'Usuario no encontrado.',
        ], Response::HTTP_NOT_FOUND);
    }

    private function usuarioId(): ?int
    {
        $id = Auth::id();

        return is_int($id) ? $id : null;
    }
}
