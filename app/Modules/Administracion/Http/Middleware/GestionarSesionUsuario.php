<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Middleware;

use App\Modules\Administracion\Domain\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class GestionarSesionUsuario
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::guard('web')->user();

        if (! $usuario instanceof User) {
            return $next($request);
        }

        $ahora = now()->getTimestamp();
        $ultimaActividad = $request->session()->get('usuario_ultima_actividad');
        $limite = max(1, (int) config('auth_security.inactividad_minutos', 15)) * 60;

        if (is_int($ultimaActividad) && $ahora - $ultimaActividad >= $limite) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'La sesión venció por inactividad. Inicia sesión nuevamente.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return $next($request);
        }

        $request->session()->put('usuario_ultima_actividad', $ahora);

        return $next($request);
    }
}
