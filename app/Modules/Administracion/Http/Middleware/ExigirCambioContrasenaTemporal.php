<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Middleware;

use App\Modules\Administracion\Domain\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ExigirCambioContrasenaTemporal
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::guard('web')->user();

        if (
            ! $request->is('api/*')
            || ! $usuario instanceof User
            || $usuario->getAttribute('password_changed_at') !== null
            || in_array($request->path(), [
                'api/auth/sesion',
                'api/auth/cambiar-contrasena',
                'api/auth/logout',
            ], true)
        ) {
            return $next($request);
        }

        return response()->json([
            'code' => 'PASSWORD_CHANGE_REQUIRED',
            'message' => 'Debes cambiar tu contraseña temporal antes de continuar.',
        ], Response::HTTP_LOCKED);
    }
}
