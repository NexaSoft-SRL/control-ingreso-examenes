<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\AuthenticateUser;
use App\Modules\Administracion\Application\Actions\CambiarContrasena;
use App\Modules\Administracion\Application\Actions\ConsultarSesion;
use App\Modules\Administracion\Application\Actions\RestablecerContrasena;
use App\Modules\Administracion\Application\Actions\SolicitarRestablecimientoContrasena;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\DTOs\SesionData;
use App\Modules\Administracion\Domain\Exceptions\ContrasenaTemporalVencidaException;
use App\Modules\Administracion\Http\Requests\CambiarContrasenaRequest;
use App\Modules\Administracion\Http\Requests\LoginRequest;
use App\Modules\Administracion\Http\Requests\RestablecerContrasenaRequest;
use App\Modules\Administracion\Http\Requests\SolicitarRestablecimientoRequest;
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
        /** @var array{usuario: string, password: string} $credentials */
        $credentials = $request->validated();

        try {
            $user = $this->authenticateUser->execute(
                $credentials['usuario'],
                $credentials['password'],
                $request->ip(),
                $request->userAgent(),
            );
        } catch (ContrasenaTemporalVencidaException) {
            return response()->json([
                'code' => 'TEMPORARY_PASSWORD_EXPIRED',
                'message' => 'La contraseña temporal venció. Solicita al administrador que restablezca tu acceso.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user === null) {
            $minutos = $this->authenticateUser->minutosDeBloqueo($credentials['usuario']);

            // Sin usuario no hay a quien atribuir el intento: queda
            // asentado con el identificador que se probo.
            $this->bitacora->registrar(
                null,
                'sesion.fallida',
                'usuarios',
                null,
                sprintf('Intento fallido con el identificador %s.', $credentials['usuario']),
            );

            if ($minutos !== null) {
                return response()->json([
                    'message' => 'La cuenta está bloqueada temporalmente.',
                    'minutos' => $minutos,
                ], 423);
            }

            $intentosRestantes = $this->authenticateUser->intentosRestantes($credentials['usuario']);

            return response()->json([
                'message' => 'Credenciales incorrectas.',
                ...($intentosRestantes === null ? [] : ['intentos_restantes' => $intentosRestantes]),
            ], Response::HTTP_UNAUTHORIZED);
        }

        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->put('usuario_ultima_actividad', now()->getTimestamp());

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

    public function cambiarContrasena(
        CambiarContrasenaRequest $request,
        CambiarContrasena $cambiar,
    ): JsonResponse {
        $usuarioId = Auth::id();
        $actual = $request->string('contrasena_actual')->toString();
        $nueva = $request->string('password')->toString();

        if (
            ! is_int($usuarioId)
            || ! $cambiar->execute(
                $usuarioId,
                $actual,
                $nueva,
            )
        ) {
            return response()->json([
                'message' => 'La contraseña actual no es correcta.',
                'errors' => ['contrasena_actual' => ['La contraseña actual no es correcta.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $request->session()->put('usuario_ultima_actividad', now()->getTimestamp());
        $sesion = $this->consultarSesion->execute($usuarioId);

        if ($sesion === null) {
            return $this->sinSesion();
        }

        $this->bitacora->registrar(
            $usuarioId,
            'contrasena.cambiar',
            'usuarios',
            $usuarioId,
            null,
        );

        return response()->json([
            'message' => 'La contraseña se actualizó correctamente.',
            'user' => $this->usuario($sesion),
        ]);
    }

    public function solicitarRestablecimiento(
        SolicitarRestablecimientoRequest $request,
        SolicitarRestablecimientoContrasena $solicitar,
    ): JsonResponse {
        $solicitar->execute($request->string('usuario')->toString());

        return response()->json([
            'message' => 'Si la cuenta tiene ese correo de contacto, enviaremos instrucciones para recuperar el acceso.',
        ]);
    }

    public function restablecerContrasena(
        RestablecerContrasenaRequest $request,
        RestablecerContrasena $restablecer,
    ): JsonResponse {
        $correo = $request->string('correo')->toString();
        $token = $request->string('token')->toString();
        $password = $request->string('password')->toString();

        if (! $restablecer->execute($correo, $token, $password)) {
            return response()->json([
                'message' => 'El enlace de recuperación no es válido o venció. Solicita uno nuevo.',
                'errors' => ['token' => ['El enlace de recuperación no es válido o venció.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'La contraseña se restableció. Ya puedes iniciar sesión.',
        ]);
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
        $inactividad = config('auth_security.inactividad_minutos', 15);

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
            'debe_cambiar_contrasena' => $sesion->debeCambiarContrasena,
            'sesion_expira_en_minutos' => is_numeric($inactividad) ? (int) $inactividad : 15,
            'docente_id' => $sesion->docenteId,
            'name' => $sesion->nombre,
            'email' => $sesion->correo ?? '',
        ];
    }
}
