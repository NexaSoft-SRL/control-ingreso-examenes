<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\CambiarContrasena;
use App\Modules\Administracion\Application\Actions\RestablecerContrasena;
use App\Modules\Administracion\Application\Actions\SolicitarRestablecimiento;
use App\Modules\Administracion\Domain\Exceptions\ContrasenaNoAceptadaException;
use App\Modules\Administracion\Http\Requests\CambiarContrasenaRequest;
use App\Modules\Administracion\Http\Requests\RestablecerContrasenaRequest;
use App\Modules\Administracion\Http\Requests\SolicitarRestablecimientoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class ContrasenaController
{
    public function __construct(
        private readonly CambiarContrasena $cambiarContrasena,
        private readonly SolicitarRestablecimiento $solicitarRestablecimiento,
        private readonly RestablecerContrasena $restablecerContrasena,
    ) {}

    public function cambiar(CambiarContrasenaRequest $request): JsonResponse
    {
        /** @var array{contrasena_actual?: string|null, contrasena_nueva: string} $datos */
        $datos = $request->validated();

        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->cambiarContrasena->execute(
                $usuarioId,
                $datos['contrasena_actual'] ?? null,
                $datos['contrasena_nueva'],
            );
        } catch (ContrasenaNoAceptadaException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'errors' => [
                    $excepcion->campo => [$excepcion->getMessage()],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Contraseña actualizada.',
        ]);
    }

    public function solicitarRestablecimiento(
        SolicitarRestablecimientoRequest $request,
    ): JsonResponse {
        /** @var array{usuario: string} $datos */
        $datos = $request->validated();

        $this->solicitarRestablecimiento->execute($datos['usuario']);

        // Respuesta unica, exista o no la cuenta y tenga o no correo.
        return response()->json([
            'message' => 'Si la cuenta tiene un correo registrado, le enviamos un enlace.',
        ]);
    }

    public function restablecer(RestablecerContrasenaRequest $request): JsonResponse
    {
        /** @var array{usuario: string, token: string, contrasena_nueva: string} $datos */
        $datos = $request->validated();

        $restablecida = $this->restablecerContrasena->execute(
            $datos['usuario'],
            $datos['token'],
            $datos['contrasena_nueva'],
        );

        if (! $restablecida) {
            return response()->json([
                'message' => 'El enlace no es válido o ya venció. Solicite uno nuevo.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Contraseña restablecida. Ya puede iniciar sesión.',
        ]);
    }
}
