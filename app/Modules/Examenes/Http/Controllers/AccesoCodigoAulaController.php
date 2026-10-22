<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Contracts\AccesoCodigoAulaGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

final class AccesoCodigoAulaController
{
    public function ingresar(Request $request, AccesoCodigoAulaGateway $gateway): JsonResponse
    {
        $limiterKey = 'intento-codigo-aula:'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            return response()->json([
                'message' => 'Demasiados intentos',
                'codigo' => 'DEMASIADOS_INTENTOS',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $validator = Validator::make($request->all(), [
            'codigo' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'nombre_colaborador' => ['required', 'string', 'min:3', 'max:120'],
        ], [
            'codigo.required' => 'Seis dígitos',
            'codigo.regex' => 'Seis dígitos',
            'nombre_colaborador.required' => 'Mínimo 3 caracteres',
            'nombre_colaborador.min' => 'Mínimo 3 caracteres',
            'nombre_colaborador.max' => 'Máximo 120 caracteres',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $codigo = trim($request->string('codigo')->toString());
        $nombreColaborador = trim($request->string('nombre_colaborador')->toString());

        $resultado = $gateway->ingresar($codigo, $nombreColaborador);

        if ($resultado === null) {
            RateLimiter::hit($limiterKey, 300);

            return response()->json([
                'message' => 'Código no válido',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        RateLimiter::clear($limiterKey);

        return response()->json([
            'message' => 'Acceso concedido',
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

    public function sesion(Request $request, AccesoCodigoAulaGateway $gateway): JsonResponse
    {
        $token = $this->extraerToken($request);

        if ($token === null || $token === '') {
            return response()->json(['message' => 'Código no válido'], Response::HTTP_UNAUTHORIZED);
        }

        $resultado = $gateway->sesion($token);

        if ($resultado['estado'] === 'finalizado') {
            return response()->json(['message' => 'El examen ha finalizado'], Response::HTTP_UNAUTHORIZED);
        }

        if ($resultado['estado'] === 'no_valido' || ! isset($resultado['data'])) {
            return response()->json(['message' => 'Código no válido'], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'data' => $resultado['data'],
        ]);
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
