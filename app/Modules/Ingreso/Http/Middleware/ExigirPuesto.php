<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Middleware;

use App\Modules\Administracion\Application\Contracts\RolGateway;
use App\Modules\Ingreso\Application\Contracts\AccesoAulaGateway;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as RespuestaBase;

/**
 * Guarda las rutas de la puerta. Deja pasar a una cuenta con el permiso
 * `punto_control` (o con alguno de los que la ruta indique como
 * alternativa: `puesto:seguimiento_vivo`) y a una sesion abierta con
 * codigo de aula, que solo alcanza a su aula.
 *
 * Deja al operador en el atributo `operador` de la peticion.
 */
final class ExigirPuesto
{
    public const PERMISO = 'punto_control';

    public const SESION = 'acceso_aula_id';

    public const ATRIBUTO = 'operador';

    public function __construct(
        private readonly RolGateway $roles,
        private readonly AccesoAulaGateway $accesos,
    ) {}

    /**
     * @param  Closure(Request): RespuestaBase  $next
     */
    public function handle(Request $request, Closure $next, string ...$alternativos): RespuestaBase
    {
        $usuarioId = Auth::id();

        if (is_int($usuarioId)) {
            $vigentes = $this->roles->permisosVigentesDe($usuarioId);

            if (array_intersect([self::PERMISO, ...$alternativos], $vigentes) === []) {
                return response()->json([
                    'message' => 'Este rol no tiene permiso para esta sección.',
                    'permiso_requerido' => self::PERMISO,
                ], Response::HTTP_FORBIDDEN);
            }

            $request->attributes->set(self::ATRIBUTO, new OperadorData($usuarioId));

            // Quien esta en cada puerta: lo ve el docente en «En curso».
            $aula = $request->route('aula');

            if (is_numeric($aula)) {
                $this->accesos->marcarPresencia((int) $aula, $usuarioId);
            }

            return $next($request);
        }

        $accesoId = $request->hasSession() ? $request->session()->get(self::SESION) : null;
        $acceso = is_int($accesoId) ? $this->accesos->vigente($accesoId) : null;

        if ($acceso === null) {
            // Sin autorizar todavia o cortado, no entra; la sesion se
            // conserva para que quien espera vea su estado. Con el examen
            // terminado ya no hay nada que esperar.
            $enEspera = is_int($accesoId) ? $this->accesos->deSesion($accesoId) : null;

            if ($accesoId !== null && $enEspera === null) {
                $request->session()->forget(self::SESION);
            }

            return response()->json([
                'message' => $enEspera?->estado === 'PENDIENTE'
                    ? 'Falta que el docente autorice el acceso.'
                    : 'No hay una sesión activa.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $aula = $request->route('aula');

        if ($aula !== null && (! is_numeric($aula) || (int) $aula !== $acceso->aulaId)) {
            return response()->json([
                'message' => 'El código solo abre la puerta de su aula.',
                'alcance' => true,
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set(self::ATRIBUTO, $acceso->comoOperador());
        $this->accesos->tocar($acceso->id);

        return $next($request);
    }
}
