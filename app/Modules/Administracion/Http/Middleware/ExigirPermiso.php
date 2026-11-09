<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Middleware;

use App\Modules\Administracion\Application\Authorization\VerificarPermiso;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as RespuestaBase;

/**
 * Niega el paso con un motivo explicito cuando el rol del usuario no tiene
 * el permiso vigente (criterio 3 de HU-02): nunca una pantalla en blanco.
 */
final class ExigirPermiso
{
    public function __construct(
        private readonly VerificarPermiso $verificarPermiso,
    ) {}

    /**
     * @param  Closure(Request): RespuestaBase  $next
     */
    public function handle(Request $request, Closure $next, string $permiso): RespuestaBase
    {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->verificarPermiso->puede($usuarioId, $permiso)) {
            return response()->json([
                'message' => 'Este rol no tiene permiso para esta sección.',
                'permiso_requerido' => $permiso,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
