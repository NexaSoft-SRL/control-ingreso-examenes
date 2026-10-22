<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;
use App\Modules\Examenes\Application\Contracts\AuxiliaresDocenteGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class AuxiliaresController
{
    public function index(Request $request, AuxiliaresDocenteGateway $gateway): JsonResponse
    {
        $usuarioId = (int) Auth::id();
        $buscar = trim($request->string('buscar')->toString() ?: $request->string('q')->toString());

        if (mb_strlen($buscar) >= 3) {
            $data = $gateway->buscar($buscar);

            return response()->json([
                'data' => $data,
                'meta' => ['cantidad' => count($data)],
            ]);
        }

        $data = $gateway->listarDelDocente($usuarioId);

        return response()->json([
            'data' => $data,
            'meta' => ['cantidad' => count($data)],
        ]);
    }

    public function store(
        Request $request,
        AuxiliaresDocenteGateway $gateway,
        CuentaUsuarioGateway $cuentas,
        BitacoraGateway $bitacora,
    ): JsonResponse {
        $usuarioId = (int) Auth::id();

        if ($request->has('auxiliar_id')) {
            $auxiliarId = $request->integer('auxiliar_id');
            $auxUsuario = DB::table('usuarios as u')
                ->join('roles as r', 'r.id', '=', 'u.role_id')
                ->where('u.id', $auxiliarId)
                ->where('r.name', 'Auxiliar')
                ->where('u.is_active', true)
                ->first(['u.id', 'u.nombre', 'u.usuario', 'u.correo']);

            if ($auxUsuario === null) {
                return response()->json(['message' => 'Auxiliar no encontrado.'], Response::HTTP_NOT_FOUND);
            }

            $gateway->agregar($usuarioId, (int) $auxUsuario->id);

            $bitacora->registrar(
                $usuarioId,
                'auxiliar.agregar',
                'docente_auxiliares',
                (int) $auxUsuario->id,
                "Auxiliar agregado: {$auxUsuario->nombre} ({$auxUsuario->usuario})",
            );

            return response()->json([
                'message' => 'Auxiliar agregado',
                'data' => [
                    'id' => (int) $auxUsuario->id,
                    'nombre' => (string) $auxUsuario->nombre,
                    'codigo' => (string) $auxUsuario->usuario,
                    'correo' => (string) $auxUsuario->correo,
                ],
            ], Response::HTTP_OK);
        }

        $request->validate([
            'nombre_completo' => ['required', 'string', 'min:3'],
            'codigo_estudiante' => ['required', 'string', 'regex:/^[0-9]{5,10}$/'],
        ], [
            'nombre_completo.required' => 'Obligatorio',
            'nombre_completo.min' => 'Mínimo 3 caracteres',
            'codigo_estudiante.required' => 'Obligatorio',
            'codigo_estudiante.regex' => 'Entre 5 y 10 dígitos',
        ]);

        $nombre = trim($request->string('nombre_completo')->toString());
        $codigo = trim($request->string('codigo_estudiante')->toString());
        $correo = strtolower("{$codigo}@est.umss.edu");

        $existente = $gateway->buscarPorCodigoOCorreo($codigo, $correo);

        if ($existente !== null) {
            if ($existente['rol_nombre'] === 'Auxiliar') {
                $gateway->agregar($usuarioId, $existente['id']);

                return response()->json([
                    'message' => 'Auxiliar ya registrado',
                    'data' => [
                        'id' => $existente['id'],
                        'nombre' => $existente['nombre'],
                        'codigo' => $codigo,
                        'correo' => $correo,
                    ],
                ], Response::HTTP_OK);
            }

            return response()->json([
                'message' => 'Ya existe',
                'errors' => [
                    'codigo_estudiante' => ['Ya existe'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $cuentaData = new NuevaCuentaData(
            nombre: $nombre,
            usuario: $codigo,
            correo: $correo,
            rol: NuevaCuentaData::ROL_AUXILIAR,
        );

        $cuenta = $cuentas->crear($cuentaData, $usuarioId);

        $gateway->agregar($usuarioId, $cuenta->id);

        $bitacora->registrar(
            $usuarioId,
            'auxiliar.agregar',
            'docente_auxiliares',
            $cuenta->id,
            "Auxiliar agregado: {$nombre} ({$codigo})",
        );

        return response()->json([
            'message' => 'Auxiliar agregado',
            'data' => [
                'id' => $cuenta->id,
                'nombre' => $nombre,
                'codigo' => $codigo,
                'correo' => $correo,
            ],
        ], Response::HTTP_CREATED);
    }

    public function destroy(
        int $auxiliar,
        AuxiliaresDocenteGateway $gateway,
        BitacoraGateway $bitacora,
    ): JsonResponse {
        $usuarioId = (int) Auth::id();

        if (! $gateway->estaEnLista($usuarioId, $auxiliar)) {
            if (! $gateway->existeUsuario($auxiliar)) {
                return response()->json(['message' => 'Auxiliar no encontrado.'], Response::HTTP_NOT_FOUND);
            }

            return response()->json(['message' => 'El auxiliar no pertenece a la lista.'], Response::HTTP_FORBIDDEN);
        }

        if ($gateway->tieneExamenPendiente($usuarioId, $auxiliar)) {
            return response()->json([
                'message' => 'Atiende un examen pendiente',
                'codigo' => 'AUXILIAR_EN_EXAMEN_PENDIENTE',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $auxUsuario = DB::table('usuarios')->where('id', $auxiliar)->first(['nombre', 'usuario']);
        $nombreAuxiliar = $auxUsuario ? "{$auxUsuario->nombre} ({$auxUsuario->usuario})" : (string) $auxiliar;

        $gateway->quitar($usuarioId, $auxiliar);

        $bitacora->registrar(
            $usuarioId,
            'auxiliar.quitar',
            'docente_auxiliares',
            $auxiliar,
            "Auxiliar quitado: {$nombreAuxiliar}",
        );

        return response()->json(['message' => 'Auxiliar quitado']);
    }
}
