<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\AuthenticateUser;
use App\Modules\Administracion\Application\Actions\CerrarSesion;
use App\Modules\Administracion\Application\Actions\ConsultarSesion;
use App\Modules\Administracion\Application\DTOs\ResultadoAccesoData;
use App\Modules\Administracion\Application\DTOs\SesionData;
use App\Modules\Administracion\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticationController
{
    public function __construct(
        private readonly AuthenticateUser $authenticateUser,
        private readonly CerrarSesion $cerrarSesion,
        private readonly ConsultarSesion $consultarSesion,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        /** @var array{usuario: string, password: string} $credentials */
        $credentials = $request->validated();

        $resultado = $this->authenticateUser->execute(
            $credentials['usuario'],
            $credentials['password'],
            $request->ip(),
            $request->userAgent(),
        );

        $usuarioId = $resultado->usuarioId;

        if (! $resultado->esCorrecto() || $usuarioId === null) {
            return $this->rechazo($resultado);
        }

        Auth::loginUsingId($usuarioId);

        $request->session()->regenerate();

        $sesion = $this->consultarSesion->execute($usuarioId);

        if ($sesion === null) {
            return $this->sinSesion();
        }

        return response()->json([
            'message' => 'Autenticación correcta.',
            'user' => $this->usuario($sesion),
        ]);
    }

    public function sesion(Request $request): JsonResponse
    {
        $usuarioId = Auth::id();

        $sesion = is_int($usuarioId)
            ? $this->consultarSesion->execute($usuarioId)
            : null;

        if ($sesion === null) {
            // Una cuenta bloqueada con la sesion abierta la pierde aqui.
            if (is_int($usuarioId)) {
                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return $this->sinSesion();
        }

        return response()->json([
            'user' => $this->usuario($sesion),
        ]);
    }

    public function logout(Request $request): Response
    {
        $usuarioId = Auth::id();

        $this->cerrarSesion->execute(
            is_int($usuarioId) ? $usuarioId : null
        );

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function rechazo(ResultadoAccesoData $resultado): JsonResponse
    {
        if ($resultado->estado === ResultadoAccesoData::BLOQUEADA) {
            return response()->json([
                'message' => 'Cuenta bloqueada.',
                'minutos' => $resultado->minutosBloqueo,
            ], Response::HTTP_LOCKED);
        }

        if ($resultado->estado === ResultadoAccesoData::INACTIVA) {
            return response()->json([
                'message' => 'Cuenta bloqueada.',
            ], Response::HTTP_LOCKED);
        }

        if ($resultado->estado === ResultadoAccesoData::TEMPORAL_VENCIDA) {
            return response()->json([
                'message' => 'La contraseña temporal venció. Pida una nueva a la administración.',
                'codigo' => 'TEMPORAL_VENCIDA',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $cuerpo = ['message' => 'Usuario o contraseña incorrectos.'];

        // Solo si la cuenta existe (decision D-18).
        if ($resultado->intentosRestantes !== null) {
            $cuerpo['intentos_restantes'] = $resultado->intentosRestantes;
        }

        return response()->json($cuerpo, Response::HTTP_UNAUTHORIZED);
    }

    private function sinSesion(): JsonResponse
    {
        return response()->json([
            'message' => 'No hay una sesión activa.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array<string, mixed>
     */
    private function usuario(SesionData $sesion): array
    {
        return [
            'id' => $sesion->id,
            'nombre' => $sesion->nombre,
            'usuario' => $sesion->usuario,
            'correo' => $sesion->correo,
            'rol' => $sesion->rol,
            'permisos' => $sesion->permisos,
            'debe_cambiar_contrasena' => $sesion->debeCambiarContrasena,
            'docente_id' => $sesion->docenteId,
        ];
    }
}
