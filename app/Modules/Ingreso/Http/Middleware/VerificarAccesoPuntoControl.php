<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Middleware;

use App\Modules\Ingreso\Application\Contracts\VerificacionPuntoControlGateway;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class VerificarAccesoPuntoControl
{
    public function __construct(
        private readonly VerificacionPuntoControlGateway $gateway,
    ) {}

    /**
     * @param  Closure(Request): (Response|JsonResponse)  $next
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $token = $this->extraerToken($request);

        if ($token !== null && $token !== '') {
            $routeExamen = (int) $request->route('examen');
            $routeAula = (int) $request->route('aula');

            $resultado = $this->gateway->verificarAccesoPorToken($token, $routeExamen, $routeAula);

            if ($resultado['estado'] === 'aula_denegada') {
                return response()->json([
                    'message' => 'Punto de control no autorizado.',
                ], Response::HTTP_FORBIDDEN);
            }

            if ($resultado['estado'] === 'finalizado') {
                return response()->json([
                    'message' => 'El examen ha finalizado',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if ($resultado['estado'] === 'no_valido' || ! isset($resultado['acceso'])) {
                return response()->json([
                    'message' => 'Código no válido',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $request->attributes->set('acceso_codigo_aula', $resultado['acceso']);

            return $next($request);
        }

        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->gateway->tienePermisoPuntoControl($usuarioId)) {
            $rol = $this->gateway->rolDelUsuario($usuarioId);

            return response()->json([
                'message' => sprintf(
                    'Tu rol (%s) no tiene acceso a esta sección. Pide al administrador que le habilite el permiso.',
                    $rol,
                ),
                'permiso_requerido' => 'punto_control',
                'rol' => $rol,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function extraerToken(Request $request): ?string
    {
        $token = $request->header('X-Codigo-Aula-Token');
        if (is_string($token) && $token !== '') {
            return trim($token);
        }

        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            return trim($bearer);
        }

        $input = $request->input('token');

        return is_string($input) && $input !== '' ? trim($input) : null;
    }
}
