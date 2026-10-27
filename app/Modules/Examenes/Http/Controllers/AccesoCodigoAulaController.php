<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Contracts\AccesoCodigoAulaGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

/**
 * La puerta con codigo de aula, para quien controla sin cuenta (HU-18). El
 * acceso espera la autorizacion del docente; mientras, la pantalla pregunta
 * por su estado.
 */
final class AccesoCodigoAulaController
{
    // Cinco codigos equivocados cada cinco minutos por direccion. Solo
    // cuentan los errados: varios auxiliares detras de la misma red entran
    // sin bloquearse entre si.
    private const FALLOS = 5;

    private const VENTANA_SEGUNDOS = 300;

    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    // Un nombre de persona: es la firma de lo que registre.
    private const NOMBRE = "/^(?=(?:.*\\p{L}){3})\\p{L}+(?:(?:\\.\\s*|\\s+|['-])\\p{L}+)*\\.?$/u";

    public function ingresar(Request $request, AccesoCodigoAulaGateway $gateway): JsonResponse
    {
        $clave = 'intento-codigo-aula:'.$request->ip();

        if (RateLimiter::tooManyAttempts($clave, self::FALLOS)) {
            $segundos = RateLimiter::availableIn($clave);

            return response()->json([
                'message' => 'Demasiados intentos.',
                'codigo' => 'DEMASIADOS_INTENTOS',
                'reintentar_en' => $segundos,
            ], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $segundos]);
        }

        $codigo = trim($request->string('codigo')->toString());
        $nombre = trim($request->string('nombre_colaborador')->toString());

        $errores = [];

        if (preg_match('/^[0-9]{6}$/', $codigo) !== 1) {
            $errores['codigo'] = ['Seis dígitos'];
        }

        if ($nombre === '') {
            $errores['nombre_colaborador'] = ['Obligatorio'];
        } elseif (mb_strlen($nombre) > 120) {
            $errores['nombre_colaborador'] = ['Máximo 120 caracteres'];
        } elseif (preg_match(self::NOMBRE, $nombre) !== 1) {
            $errores['nombre_colaborador'] = ['Mínimo 3 letras, sin signos seguidos'];
        }

        if ($errores !== []) {
            return response()->json([
                'message' => reset($errores)[0],
                'errors' => $errores,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $resultado = $gateway->ingresar($codigo, $nombre);

        if ($resultado === null) {
            RateLimiter::hit($clave, self::VENTANA_SEGUNDOS);
            $mensaje = $this->fueraDeHora($gateway->ventanaDelCodigo($codigo));

            return response()->json([
                'message' => $mensaje,
                'errors' => ['codigo' => [$mensaje]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        RateLimiter::clear($clave);

        return response()->json([
            'message' => 'Esperando autorización',
            'data' => $resultado,
        ], Response::HTTP_OK);
    }

    public function salir(Request $request, AccesoCodigoAulaGateway $gateway): JsonResponse
    {
        $token = $this->extraerToken($request);

        if ($token !== null && $token !== '') {
            $gateway->salir($token);
        }

        return response()->json(['message' => 'Sesión cerrada']);
    }

    /**
     * Como va el acceso: pendiente o autorizado, con sus datos; rechazado,
     * cortado o con el examen cerrado, 401 con el motivo.
     */
    public function sesion(Request $request, AccesoCodigoAulaGateway $gateway): JsonResponse
    {
        $token = $this->extraerToken($request);
        $resultado = $token === null || $token === '' ? ['estado' => 'no_valido'] : $gateway->sesion($token);

        if ($resultado['estado'] !== 'ok' || ! isset($resultado['data'])) {
            return response()->json([
                'message' => match ($resultado['estado']) {
                    'rechazado' => 'El docente no autorizó el acceso.',
                    'cortado' => 'El docente cortó el acceso.',
                    'finalizado' => 'El examen ha finalizado.',
                    default => 'Código no válido.',
                },
                'estado' => $resultado['estado'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json(['data' => $resultado['data']]);
    }

    /**
     * Un codigo de verdad fuera de hora dice cuando vale: «La puerta abre el
     * 23 oct a las 09:12.» o «El examen ya terminó: cerró el 23 oct a las
     * 11:00.».
     *
     * @param  array{abre: string, cierra: string, cerrada: bool}|null  $ventana
     */
    private function fueraDeHora(?array $ventana): string
    {
        if ($ventana === null) {
            return 'Código no válido.';
        }

        return $ventana['cerrada']
            ? sprintf('El examen ya terminó: cerró el %s.', $this->cuando($ventana['cierra']))
            : sprintf('La puerta abre el %s.', $this->cuando($ventana['abre']));
    }

    // '2026-10-23 09:12' → '23 oct a las 09:12'.
    private function cuando(string $instante): string
    {
        if (preg_match('/^\d{4}-(\d{2})-(\d{2}) (\d{2}:\d{2})/', $instante, $partes) !== 1) {
            return $instante;
        }

        return sprintf('%d %s a las %s', (int) $partes[2], self::MESES[(int) $partes[1] - 1] ?? $partes[1], $partes[3]);
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
