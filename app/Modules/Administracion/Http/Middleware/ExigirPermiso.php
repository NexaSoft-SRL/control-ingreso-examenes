<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Middleware;

use App\Modules\Administracion\Application\Authorization\VerificarPermiso;
use App\Modules\Administracion\Domain\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Niega el paso cuando el rol del usuario no tiene el permiso de la ruta.
 * La negativa es explícita —dice qué permiso falta y a quién pedirlo— porque
 * el backlog no admite una pantalla en blanco.
 */
final class ExigirPermiso
{
    public function __construct(
        private readonly VerificarPermiso $verificar,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        $usuario = Auth::guard('web')->user();

        if (! $usuario instanceof User) {
            return response()->json([
                'message' => 'Debes iniciar sesión.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->verificar->puede($usuario, $permiso)) {
            $rol = $usuario->role->name ?? 'sin rol asignado';

            return response()->json([
                'message' => sprintf(
                    'Tu rol (%s) no tiene acceso a esta sección. Pide al administrador que le habilite el permiso.',
                    $rol,
                ),
                'permiso_requerido' => $permiso,
                'rol' => $usuario->role?->name,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
