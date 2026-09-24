<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\AuthenticateUser;
use App\Modules\Administracion\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticationController
{
    public function __construct(
        private readonly AuthenticateUser $authenticateUser,
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
            return response()->json([
                'message' => 'Credenciales incorrectas.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        Auth::login($user);

return response()->json([
    'message' => 'Autenticación correcta.',
    'user' => [
        'id' => $user->getKey(),
        'name' => $user->name,
        'email' => $user->email,
    ],
]);
    }

    public function logout(Request $request): Response
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
