<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\AuthenticateUser;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticationController
{
    public function __construct(
        private readonly AuthenticateUser $authenticateUser,
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

        return response()->json([
            'message' => 'Autenticación correcta.',
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->role?->name,
                // El cliente oculta con esto las secciones que el rol no
                // puede abrir; quien las fuerce igual recibe un 403.
                'permisos' => $user->role?->permissions
                    ->pluck('name')
                    ->values()
                    ->all() ?? [],
            ],
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
}
