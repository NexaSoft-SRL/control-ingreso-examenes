<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\AuthenticateUser;
use App\Modules\Administracion\Application\Actions\ConsultarSesion;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
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
        private readonly ConsultarSesion $consultarSesion,
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        /** @var array{email: string, password: string} $credentials */
        $credentials = $request->validated();

        $user = $this->authenticateUser->execute(
            $credentials['email'],
            $credentials['password'],
            $request->ip(),
            $request->userAgent(),
        );

        if ($user === null) {
            // Sin usuario no hay a quien atribuir el intento: queda
            // asentado con el identificador que se probo.
            $this->bitacora->registrar(
                null,
                'sesion.fallida',
                'usuarios',
                null,
                sprintf('Intento fallido con el identificador %s.', $credentials['email']),
            );

            return response()->json([
                'message' => 'Credenciales incorrectas.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        Auth::login($user);

        $request->session()->regenerate();

        $id = $user->getKey();

        $this->bitacora->registrar(
            is_int($id) ? $id : null,
            'sesion.iniciar',
            'usuarios',
            is_int($id) ? $id : null,
            null,
        );

        $sesion = is_int($id) ? $this->consultarSesion->execute($id) : null;

        if ($sesion === null) {
            return $this->sinSesion();
        }

        return response()->json([
            'message' => 'Autenticación correcta.',
            'user' => $this->usuario($sesion),
        ]);
    }

    /**
     * La fuente de verdad de la sesion del cliente. No exige el middleware
     * `auth`: responde ella misma el 401.
     */
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
        // El identificador se toma antes de cerrar la sesion: despues ya no
        // hay usuario a quien atribuir la operacion.
        $user = Auth::guard('web')->user();
        $id = $user?->getKey();

        Auth::logout();

        $this->bitacora->registrar(
            is_int($id) ? $id : null,
            'sesion.cerrar',
            'usuarios',
            is_int($id) ? $id : null,
            null,
        );

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function sinSesion(): JsonResponse
    {
        return response()->json([
            'message' => 'No hay una sesión activa.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * El mismo objeto en el acceso y en la consulta de sesion. `name` y
     * `email` repiten `nombre` y `correo` para los clientes anteriores.
     *
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
            // El cliente oculta con esto las vistas que el rol no puede
            // abrir; quien las fuerce igual recibe un 403.
            'permisos' => $sesion->permisos,
            // El cambio obligatorio de la temporal llega con HU-16.
            'debe_cambiar_contrasena' => false,
            'docente_id' => $sesion->docenteId,
            'name' => $sesion->nombre,
            'email' => $sesion->correo ?? '',
        ];
    }
}
