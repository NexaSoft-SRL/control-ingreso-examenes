<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Middleware;

use App\Modules\Administracion\Application\Contracts\RolGateway;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as RespuestaBase;

/**
 * Deja pasar a quien tenga vigente al menos uno de los permisos pedidos:
 * hay catalogos de la oferta que usan dos pantallas con permisos
 * distintos, y el middleware `permiso` acepta uno solo.
 */
final class ExigirAlgunPermiso
{
    public function __construct(
        private readonly RolGateway $roles,
    ) {}

    /**
     * @param  Closure(Request): RespuestaBase  $next
     */
    public function handle(Request $request, Closure $next, string ...$permisos): RespuestaBase
    {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (array_intersect($permisos, $this->roles->permisosVigentesDe($usuarioId)) === []) {
            return response()->json([
                'message' => 'Este rol no tiene permiso para esta sección.',
                'permiso_requerido' => $permisos[0] ?? null,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
