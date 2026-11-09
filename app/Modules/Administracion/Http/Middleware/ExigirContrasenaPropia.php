<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Middleware;

use App\Modules\Administracion\Application\Contracts\CredencialesGateway;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as RespuestaBase;

/**
 * Mientras el usuario siga con la contrasena temporal que se le entrego, el
 * sistema solo le permite consultar su sesion, cerrarla o definir la suya.
 * Las pantallas del cliente no se bloquean: es el propio frontend el que lo
 * lleva al primer ingreso.
 */
final class ExigirContrasenaPropia
{
    /**
     * Rutas que siguen disponibles con la contrasena temporal.
     *
     * @var list<string>
     */
    private const RUTAS_PERMITIDAS = [
        'api/auth/logout',
        'api/auth/contrasena',
        'api/auth/sesion',
    ];

    public function __construct(
        private readonly CredencialesGateway $credenciales,
    ) {}

    /**
     * @param  Closure(Request): RespuestaBase  $next
     */
    public function handle(Request $request, Closure $next): RespuestaBase
    {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId) || ! $request->is('api/*')) {
            return $next($request);
        }

        foreach (self::RUTAS_PERMITIDAS as $ruta) {
            if ($request->is($ruta)) {
                return $next($request);
            }
        }

        if ($this->credenciales->debeCambiarContrasena($usuarioId)) {
            return response()->json([
                'message' => 'Defina su propia contraseña antes de continuar.',
                'debe_cambiar_contrasena' => true,
            ], Response::HTTP_CONFLICT);
        }

        return $next($request);
    }
}
