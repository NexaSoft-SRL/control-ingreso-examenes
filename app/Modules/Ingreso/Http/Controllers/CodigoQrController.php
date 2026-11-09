<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Actions\EmitirCodigosQr;
use App\Modules\Ingreso\Application\Actions\EnviarAvisos;
use App\Modules\Ingreso\Application\Actions\EnviarCodigosQr;
use App\Modules\Ingreso\Application\Actions\ReenviarCodigoQr;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\Queries\ArmarHojaDeCredenciales;
use App\Modules\Ingreso\Application\Queries\ConsultarCredencial;
use App\Modules\Ingreso\Application\Queries\ListarCodigosQr;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use App\Modules\Ingreso\Domain\Exceptions\SinCodigosException;
use App\Modules\Ingreso\Http\Requests\EnviarAvisoRequest;
use App\Modules\Ingreso\Http\Requests\FiltroCodigosRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CodigoQrController
{
    use Responde;

    public function index(
        int $examen,
        FiltroCodigosRequest $request,
        ListarCodigosQr $listar,
        PuertaGateway $puerta,
    ): JsonResponse {
        if ($puerta->examen($examen) === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            return response()->json($listar->execute($examen, $request->toData(), $this->usuarioId()));
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        }
    }

    public function store(int $examen, EmitirCodigosQr $emitir, PuertaGateway $puerta): JsonResponse
    {
        if ($puerta->examen($examen) === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            $resultado = $emitir->execute($examen, $this->usuarioId());
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (SinCodigosException $error) {
            return $this->conflicto($error->getMessage(), $error->codigo);
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
        PuertaGateway $puerta,
    ): JsonResponse {
        if ($puerta->examen($examen) === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            $credencial = $consultar->execute($examen, $estudiante, $this->usuarioId());
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        }

        if ($credencial === null) {
            return $this->noEncontrado('El estudiante no está habilitado en este examen.');
        }

        return response()->json(['data' => $credencial]);
    }

    public function enviar(
        int $examen,
        FiltroCodigosRequest $request,
        EnviarCodigosQr $enviar,
        PuertaGateway $puerta,
    ): JsonResponse {
        if ($puerta->examen($examen) === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            $resultado = $enviar->execute($examen, $request->toData(), $this->usuarioId(), $request->tanda());
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (SinCodigosException $error) {
            return $this->conflicto($error->getMessage(), $error->codigo);
        }

        return response()->json([
            'message' => $resultado['encolados'] === 1
                ? '1 código enviado.'
                : $resultado['encolados'].' códigos enviados.',
            'encolados' => $resultado['encolados'],
            'pendientes' => $resultado['pendientes'],
            'tanda' => $resultado['tanda'],
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * El codigo de un solo estudiante, lo haya recibido antes o no.
     */
    public function reenviar(
        int $examen,
        int $estudiante,
        ReenviarCodigoQr $reenviar,
        PuertaGateway $puerta,
    ): JsonResponse {
        if ($puerta->examen($examen) === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            $correo = $reenviar->execute($examen, $estudiante, $this->usuarioId());
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (SinCodigosException $error) {
            return $this->conflicto($error->getMessage(), $error->codigo);
        }

        if ($correo === null) {
            return $this->noEncontrado('El estudiante no está habilitado en este examen.');
        }

        return response()->json([
            'message' => '1 código enviado.',
            'encolados' => 1,
            'pendientes' => 0,
            'correo' => $correo,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * El aviso del examen: fecha, aula, normas y recomendaciones.
     */
    public function avisar(
        int $examen,
        EnviarAvisoRequest $request,
        EnviarAvisos $avisar,
        PuertaGateway $puerta,
    ): JsonResponse {
        if ($puerta->examen($examen) === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            $resultado = $avisar->execute(
                $examen,
                $request->toData(),
                $request->recomendaciones(),
                $this->usuarioId(),
                $request->tanda(),
            );
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        } catch (SinCodigosException $error) {
            return $this->conflicto($error->getMessage(), $error->codigo);
        }

        return response()->json([
            'message' => $resultado['encolados'] === 1
                ? '1 aviso enviado.'
                : $resultado['encolados'].' avisos enviados.',
            'encolados' => $resultado['encolados'],
            'pendientes' => $resultado['pendientes'],
            'tanda' => $resultado['tanda'],
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Hoja imprimible: el navegador la imprime o la guarda como PDF.
     */
    public function hoja(
        int $examen,
        FiltroCodigosRequest $request,
        ArmarHojaDeCredenciales $armar,
        PuertaGateway $puerta,
    ): JsonResponse|Response {
        $datos = $puerta->examen($examen);

        if ($datos === null) {
            return $this->noEncontrado('Examen no encontrado.');
        }

        try {
            $tarjetas = $armar->execute($examen, $request->toData(), $this->usuarioId());
        } catch (FueraDeAlcanceException $error) {
            return $this->fueraDeAlcance($error);
        }

        return response()
            ->view('qr.hoja', [
                'titulo' => sprintf('Credenciales · %s · %s', $datos->texto(), $datos->fecha),
                'tarjetas' => $tarjetas,
            ])
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
}
