<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Middleware;

use App\Modules\Administracion\Application\Contracts\RolGateway;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as RespuestaBase;

/**
 * La plantilla de inscritos la descargan la administracion y el docente:
 * alcanza con uno de los dos permisos. El alias `permiso` solo admite una
 * clave, por eso esta ruta lleva su propio control.
 */
final class ExigirPermisoDeLista
{
    private const PERMISOS = ['padron_estudiantes', 'mis_grupos'];

    public function __construct(
        private readonly RolGateway $roles,
    ) {}

    /**
     * @param  Closure(Request): RespuestaBase  $next
     */
    public function handle(Request $request, Closure $next): RespuestaBase
    {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (array_intersect(self::PERMISOS, $this->roles->permisosVigentesDe($usuarioId)) === []) {
            return response()->json([
                'message' => 'Este rol no tiene permiso para esta sección.',
                'permiso_requerido' => self::PERMISOS[0],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
