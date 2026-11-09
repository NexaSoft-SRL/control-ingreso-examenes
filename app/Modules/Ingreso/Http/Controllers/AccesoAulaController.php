<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\AbrirAccesoConCodigo;
use App\Modules\Ingreso\Application\Contracts\AccesoAulaGateway;
use App\Modules\Ingreso\Domain\Exceptions\CodigoDeAulaInvalidoException;
use App\Modules\Ingreso\Http\Middleware\ExigirPuesto;
use App\Modules\Ingreso\Http\Requests\AbrirAccesoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

/**
 * La puerta con codigo de aula, para quien controla sin cuenta.
 */
final class AccesoAulaController
{
    use Responde;

    private const FALLOS = 5;

    private const VENTANA_SEGUNDOS = 300;

    private function demasiados(int $segundos): JsonResponse
    {
        return response()->json([
            'message' => 'Demasiados intentos.',
            'reintentar_en' => $segundos,
        ], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $segundos]);
    }

    /**
     * Solo cuentan los codigos equivocados: varios auxiliares detras de la
     * misma red entran sin bloquearse entre si.
     */
    public function abrir(AbrirAccesoRequest $request, AbrirAccesoConCodigo $abrir): JsonResponse
    {
        $clave = 'codigo-aula:'.$request->ip();

        if (RateLimiter::tooManyAttempts($clave, self::FALLOS)) {
            return $this->demasiados(RateLimiter::availableIn($clave));
        }

        try {
            $acceso = $abrir->execute(
                $request->string('codigo')->toString(),
                $request->string('nombre')->toString(),
                $request->ip(),
            );
        } catch (CodigoDeAulaInvalidoException $error) {
            RateLimiter::hit($clave, self::VENTANA_SEGUNDOS);

            return $this->campoNoValido('codigo', $error->getMessage());
        }

        RateLimiter::clear($clave);

        $request->session()->regenerate();
        $request->session()->put(ExigirPuesto::SESION, $acceso->id);

        return response()->json(['acceso' => $acceso->comoArreglo()]);
    }

    /**
     * Como va el acceso de esta sesion: quien espera la autorizacion del
     * docente pregunta aqui. Rechazado, cortado o con el examen cerrado,
     * la sesion lo olvida.
     */
    public function estado(Request $request, AccesoAulaGateway $accesos): JsonResponse
    {
        $accesoId = $request->hasSession() ? $request->session()->get(ExigirPuesto::SESION) : null;
        $acceso = is_int($accesoId) ? $accesos->deSesion($accesoId) : null;

        if ($acceso === null || in_array($acceso->estado, ['RECHAZADO', 'CORTADO'], true)) {
            if ($accesoId !== null) {
                $request->session()->forget(ExigirPuesto::SESION);
            }

            return response()->json([
                'message' => match ($acceso?->estado) {
                    'RECHAZADO' => 'El docente no autorizó el acceso.',
                    'CORTADO' => 'El docente cortó el acceso.',
                    default => 'No hay una sesión activa.',
                },
                'estado' => $acceso === null ? 'cerrado' : mb_strtolower($acceso->estado),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json(['acceso' => $acceso->comoArreglo()]);
    }

    /**
     * Cierra el acceso de esta sesion. No falla si no habia ninguno.
     */
    public function cerrar(Request $request): Response
    {
        if ($request->hasSession()) {
            $request->session()->forget(ExigirPuesto::SESION);
            $request->session()->regenerate(true);
        }

        return response()->noContent();
    }
}
