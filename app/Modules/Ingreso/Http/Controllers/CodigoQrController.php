<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\EmitirCodigosQr;
use App\Modules\Ingreso\Application\Contracts\EmisionQrGateway;
use App\Modules\Ingreso\Application\Queries\ArmarHojaDeCredenciales;
use App\Modules\Ingreso\Application\Queries\ConsultarCredencial;
use App\Modules\Ingreso\Application\Queries\ListarCodigosQr;
use App\Modules\Ingreso\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Ingreso\Domain\Exceptions\SinCodigosException;
use App\Modules\Ingreso\Http\Requests\FiltroCodigosRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * HU-20: los codigos QR de un examen. Ademas del permiso `codigos_qr`,
 * cada accion exige ser docente del examen.
 */
final class CodigoQrController
{
    public function index(
        int $examen,
        FiltroCodigosRequest $request,
        ListarCodigosQr $listar,
        EmisionQrGateway $codigos,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return $this->sinSesion();
        }

        if ($codigos->examen($examen) === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            return response()->json($listar->execute($examen, $request->toData(), $usuarioId));
        } catch (ExamenAjenoException $excepcion) {
            return $this->ajeno($excepcion);
        }
    }

    public function store(int $examen, EmitirCodigosQr $emitir, EmisionQrGateway $codigos): JsonResponse
    {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return $this->sinSesion();
        }

        if ($codigos->examen($examen) === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            $resultado = $emitir->execute($examen, $usuarioId);
        } catch (ExamenAjenoException $excepcion) {
            return $this->ajeno($excepcion);
        } catch (SinCodigosException $excepcion) {
            return $this->conflicto($excepcion);
        }

        return response()->json([
            'message' => match ($resultado['emitidos']) {
                0 => 'Los códigos ya estaban emitidos.',
                1 => '1 código emitido.',
                default => $resultado['emitidos'].' códigos emitidos.',
            },
            'emitidos' => $resultado['emitidos'],
            'total' => $resultado['total'],
        ]);
    }

    public function show(
        int $examen,
        int $estudiante,
        ConsultarCredencial $consultar,
        EmisionQrGateway $codigos,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return $this->sinSesion();
        }

        if ($codigos->examen($examen) === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            $credencial = $consultar->execute($examen, $estudiante, $usuarioId);
        } catch (ExamenAjenoException $excepcion) {
            return $this->ajeno($excepcion);
        }

        if ($credencial === null) {
            return $this->noEncontrado('El estudiante no está habilitado en este examen.');
        }

        return response()->json(['data' => $credencial]);
    }

    /**
     * Hoja imprimible en otra pestaña: solo las credenciales, dos por fila.
     * El navegador la imprime o la guarda como PDF.
     */
    public function hoja(
        int $examen,
        FiltroCodigosRequest $request,
        ArmarHojaDeCredenciales $armar,
        EmisionQrGateway $codigos,
    ): JsonResponse|Response {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return $this->sinSesion();
        }

        $datos = $codigos->examen($examen);

        if ($datos === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            $tarjetas = $armar->execute($examen, $request->toData(), $usuarioId);
        } catch (ExamenAjenoException $excepcion) {
            return $this->ajeno($excepcion);
        }

        return response()
            ->view('qr.hoja', [
                'titulo' => sprintf('Credenciales · %s · %s', $datos->texto, $datos->fechaTexto),
                'tarjetas' => $tarjetas,
            ])
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    private function sinSesion(): JsonResponse
    {
        return response()->json([
            'message' => 'No hay una sesión activa.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function noEncontrado(string $mensaje): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
        ], Response::HTTP_NOT_FOUND);
    }

    private function ajeno(ExamenAjenoException $excepcion): JsonResponse
    {
        return response()->json([
            'message' => $excepcion->getMessage(),
            'alcance' => true,
        ], Response::HTTP_FORBIDDEN);
    }

    private function conflicto(SinCodigosException $excepcion): JsonResponse
    {
        return response()->json([
            'message' => $excepcion->getMessage(),
            'codigo' => $excepcion->codigo,
        ], Response::HTTP_CONFLICT);
    }
}
