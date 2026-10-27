<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Middleware;

use App\Modules\Ingreso\Application\Contracts\VerificacionPuntoControlGateway;
use App\Modules\Ingreso\Application\DTOs\OperadorPuertaData;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda las rutas de la puerta. Deja pasar a una cuenta con el permiso
 * `punto_control` (o con alguno de los que la ruta indique como
 * alternativa: `puesto:seguimiento_vivo`) y a quien entro con un codigo de
 * aula autorizado por el docente, que solo alcanza a su aula.
 *
 * Deja al operador en el atributo `operador` de la peticion.
 */
final class ExigirPuesto
{
    public const PERMISO = 'punto_control';

    public const ATRIBUTO = 'operador';

    public function __construct(
        private readonly VerificacionPuntoControlGateway $gateway,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$alternativos): Response
    {
        $usuarioId = Auth::id();

        if (is_int($usuarioId)) {
            if (! $this->gateway->tieneAlgunPermiso($usuarioId, array_values([self::PERMISO, ...$alternativos]))) {
                $rol = $this->gateway->rolDelUsuario($usuarioId);

                return response()->json([
                    'message' => sprintf(
                        'Su rol (%s) no tiene acceso a esta sección. Solicite el permiso al administrador.',
                        $rol,
                    ),
                    'permiso_requerido' => self::PERMISO,
                    'rol' => $rol,
                ], Response::HTTP_FORBIDDEN);
            }

            $request->attributes->set(self::ATRIBUTO, OperadorPuertaData::cuenta($usuarioId));

            return $next($request);
        }

        $token = self::token($request);
        $acceso = $token === null ? null : $this->gateway->accesoDeToken($token);

        if ($acceso === null || $acceso['estado'] !== 'autorizado') {
            return response()->json([
                'message' => match ($acceso['estado'] ?? null) {
                    'pendiente' => 'Falta que el docente autorice el acceso.',
                    'rechazado' => 'El docente no autorizó el acceso.',
                    'cortado' => 'El docente cortó el acceso.',
                    'finalizado' => 'El examen ha finalizado.',
                    default => 'No hay una sesión activa.',
                },
            ], Response::HTTP_UNAUTHORIZED);
        }

        $aula = $request->route('aula');

        if ($aula !== null && (! is_numeric($aula) || (int) $aula !== $acceso['aula_id'])) {
            return response()->json([
                'message' => 'El código solo abre la puerta de su aula.',
                'alcance' => true,
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set(self::ATRIBUTO, new OperadorPuertaData(
            usuarioId: null,
            accesoId: $acceso['id'],
            aulaId: $acceso['aula_id'],
            examenId: $acceso['examen_id'],
            nombre: $acceso['nombre'],
            aula: $acceso['aula'],
            hasta: $acceso['hasta'],
        ));

        return $next($request);
    }

    /**
     * El token del acceso con codigo de aula: la cabecera propia o Bearer.
     */
    public static function token(Request $request): ?string
    {
        $token = $request->header('X-Codigo-Aula-Token');

        if (is_string($token) && trim($token) !== '') {
            return trim($token);
        }

        $bearer = $request->bearerToken();

        return is_string($bearer) && trim($bearer) !== '' ? trim($bearer) : null;
    }

    /**
     * El operador que dejo el middleware.
     */
    public static function operador(Request $request): OperadorPuertaData
    {
        $operador = $request->attributes->get(self::ATRIBUTO);

        if ($operador instanceof OperadorPuertaData) {
            return $operador;
        }

        $usuarioId = Auth::id();

        return new OperadorPuertaData(is_int($usuarioId) ? $usuarioId : null);
    }
}
