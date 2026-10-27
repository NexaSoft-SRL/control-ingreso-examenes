<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Middleware;

use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as RespuestaBase;

/**
 * Guarda las rutas del estudiante: una sesion abierta con el codigo que
 * le llego al correo. Deja al estudiante en el atributo `estudiante`.
 */
final class ExigirEstudiante
{
    public const SESION = 'estudiante_id';

    public const ATRIBUTO = 'estudiante';

    public function __construct(
        private readonly PortalEstudianteGateway $portal,
    ) {}

    /**
     * @param  Closure(Request): RespuestaBase  $next
     */
    public function handle(Request $request, Closure $next): RespuestaBase
    {
        $id = $request->hasSession() ? $request->session()->get(self::SESION) : null;
        $estudiante = is_int($id) ? $this->portal->estudiante($id) : null;

        if ($estudiante === null) {
            if ($id !== null) {
                $request->session()->forget(self::SESION);
            }

            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $request->attributes->set(self::ATRIBUTO, $estudiante);

        return $next($request);
    }
}
