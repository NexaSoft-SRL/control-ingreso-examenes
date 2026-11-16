<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Controllers;

use App\Modules\Administracion\Application\Contracts\AlmacenImagenes;
use App\Modules\Estudiantes\Application\Actions\ConfirmarCorreoDeRegistro;
use App\Modules\Estudiantes\Application\Actions\EmpadronarEstudiante;
use App\Modules\Estudiantes\Application\Actions\EntrarComoEstudiante;
use App\Modules\Estudiantes\Application\Actions\RegistrarCarnetPropio;
use App\Modules\Estudiantes\Application\Actions\SolicitarCodigoDeAcceso;
use App\Modules\Estudiantes\Application\Actions\SubirFotoPropia;
use App\Modules\Estudiantes\Application\Contracts\CodigosQrDelEstudiante;
use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;
use App\Modules\Estudiantes\Application\Contracts\RegistroPendienteGateway;
use App\Modules\Estudiantes\Application\DTOs\EstudiantePortalData;
use App\Modules\Estudiantes\Domain\Exceptions\CarnetYaRegistradoException;
use App\Modules\Estudiantes\Domain\Exceptions\CodigoDeAccesoInvalidoException;
use App\Modules\Estudiantes\Domain\Exceptions\DocumentoEnUsoException;
use App\Modules\Estudiantes\Domain\Exceptions\FotoYaSubidaException;
use App\Modules\Estudiantes\Domain\Exceptions\FueraDePlazoDeFotoException;
use App\Modules\Estudiantes\Domain\Exceptions\ImagenNoValidaException;
use App\Modules\Estudiantes\Http\Middleware\ExigirEstudiante;
use App\Modules\Estudiantes\Http\Requests\CarnetRequest;
use App\Modules\Estudiantes\Http\Requests\EmpadronarseRequest;
use App\Modules\Estudiantes\Http\Requests\EntrarEstudianteRequest;
use App\Modules\Estudiantes\Http\Requests\FotoRequest;
use App\Modules\Estudiantes\Http\Requests\SolicitarCodigoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use LogicException;

/**
 * El estudiante sin cuenta: entra con un codigo que le llega al correo
 * institucional, ve sus examenes y sube su foto.
 */
final class PortalEstudianteController
{
    /**
     * El codigo universitario que ya probo recibir el correo y todavia no
     * esta en el padron: puede empadronarse.
     */
    private const REGISTRO = 'registro_estudiante';

    /**
     * Tres pedidos cada diez minutos por codigo SIS y cuarenta por
     * direccion: un laboratorio entero comparte la misma red.
     */
    public function solicitar(SolicitarCodigoRequest $request, SolicitarCodigoDeAcceso $solicitar): JsonResponse
    {
        $codigoUniversitario = $request->codigoUniversitario();
        $porSis = 'codigo-estudiante:'.$codigoUniversitario;
        $porRed = 'codigo-estudiante-red:'.$request->ip();

        foreach ([$porSis => 3, $porRed => 40] as $clave => $maximo) {
            if (RateLimiter::tooManyAttempts($clave, $maximo)) {
                return $this->demasiados(RateLimiter::availableIn($clave));
            }
        }

        RateLimiter::hit($porSis, 600);
        RateLimiter::hit($porRed, 600);

        $codigo = $solicitar->execute($codigoUniversitario);

        $dominio = config('umss.dominio_correo_estudiantes');

        $respuesta = [
            'message' => 'Código enviado.',
            'correo' => $this->enmascarar($codigoUniversitario).'@'.(is_string($dominio) ? $dominio : 'est.umss.edu'),
            'minutos' => SolicitarCodigoDeAcceso::MINUTOS,
        ];

        // Sin servidor de correo, en la maquina de desarrollo el codigo se
        // muestra para poder entrar.
        if (app()->environment('local') && in_array(config('mail.default'), ['log', 'array'], true)) {
            $respuesta['codigo_prueba'] = $codigo;
        }

        return response()->json($respuesta);
    }

    /**
     * Los intentos por codigo los limita la accion (cinco); aqui, los
     * fallidos por direccion.
     */
    public function entrar(
        EntrarEstudianteRequest $request,
        EntrarComoEstudiante $entrar,
        PortalEstudianteGateway $portal,
        RegistroPendienteGateway $pendientes,
        ConfirmarCorreoDeRegistro $confirmar,
    ): JsonResponse {
        $porRed = 'entrar-estudiante:'.$request->ip();

        if (RateLimiter::tooManyAttempts($porRed, 60)) {
            return $this->demasiados(RateLimiter::availableIn($porRed));
        }

        $sis = $request->codigoUniversitario();
        $hash = hash('sha256', $request->codigo());
        $existente = $portal->porCodigo($sis);

        // El del primer correo despues de un reenvio: se avisa sin gastar un
        // intento.
        $enElPadron = $existente !== null;
        $reemplazado = ! $enElPadron
            ? $pendientes->fueReemplazado($sis, $hash)
            : $portal->fueReemplazado($existente->id, $hash);

        if ($reemplazado) {
            return $this->codigoRechazado('Código reemplazado · use el del último correo', null);
        }

        try {
            // Quien aun no esta en el padron prueba su correo con el mismo
            // codigo; si coincide, puede empadronarse.
            if (! $enElPadron) {
                $confirmar->execute($sis, $request->codigo());
                $request->session()->regenerate();
                $request->session()->put(self::REGISTRO, $sis);

                return response()->json(['registro' => ['codigo_universitario' => $sis]]);
            }

            $estudiante = $entrar->execute($sis, $request->codigo());
        } catch (CodigoDeAccesoInvalidoException) {
            RateLimiter::hit($porRed, 600);

            $restantes = $enElPadron
                ? $portal->intentosRestantes($sis, EntrarComoEstudiante::INTENTOS)
                : $pendientes->intentosRestantes($sis, EntrarComoEstudiante::INTENTOS);

            return $this->codigoRechazado(match (true) {
                $restantes === null => 'Código vencido · pida otro',
                $restantes === 0 => 'Código bloqueado · pida otro',
                default => sprintf(
                    'Código incorrecto · %d %s',
                    $restantes,
                    $restantes === 1 ? 'intento restante' : 'intentos restantes',
                ),
            }, $restantes);
        }

        $request->session()->regenerate();
        $request->session()->put(ExigirEstudiante::SESION, $estudiante->id);

        return response()->json(['estudiante' => $estudiante->comoArreglo()]);
    }

    /**
     * Las carreras para elegir, solo para quien ya probo su correo.
     */
    public function carreras(Request $request, PortalEstudianteGateway $portal): JsonResponse
    {
        if ($this->sisPendiente($request) === null) {
            return $this->sinRegistro();
        }

        return response()->json(['data' => $portal->carreras()]);
    }

    /**
     * El estudiante que probo su correo se empadrona con sus datos y entra.
     */
    public function empadronarse(EmpadronarseRequest $request, EmpadronarEstudiante $empadronar): JsonResponse
    {
        $sis = $this->sisPendiente($request);

        if ($sis === null) {
            return $this->sinRegistro();
        }

        try {
            $estudiante = $empadronar->execute(
                $sis,
                $request->string('documento')->toString(),
                $request->string('nombres')->toString(),
                $request->string('apellidos')->toString(),
                $request->integer('carrera_id'),
            );
        } catch (DocumentoEnUsoException) {
            return response()->json([
                'message' => 'Ese documento ya está registrado.',
                'errors' => ['documento' => ['Ya está registrado · pida ayuda a su docente']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $request->session()->forget(self::REGISTRO);
        $request->session()->regenerate();
        $request->session()->put(ExigirEstudiante::SESION, $estudiante->id);

        return response()->json(['estudiante' => $estudiante->comoArreglo()], Response::HTTP_CREATED);
    }

    public function salir(Request $request): Response
    {
        // `regenerate(true)` destruye la sesion anterior: la cookie vieja no
        // vuelve a abrir el portal en una computadora compartida.
        if ($request->hasSession()) {
            $request->session()->forget([ExigirEstudiante::SESION, self::REGISTRO]);
            $request->session()->regenerate(true);
        }

        return response()->noContent();
    }

    public function sesion(Request $request, PortalEstudianteGateway $portal): JsonResponse
    {
        $plazo = $portal->plazo();
        $hoy = now()->toDateString();
        $estudiante = $this->estudiante($request);
        $foto = $portal->estadoFoto($estudiante->id);
        $habilitado = $foto['habilitada_hasta'] !== null && $foto['habilitada_hasta'] >= $hoy;

        return response()->json([
            'estudiante' => $estudiante->comoArreglo(),
            'plazo_foto' => $plazo === null ? null : [
                'desde' => $plazo->desde,
                'hasta' => $plazo->hasta,
                'abierto' => $plazo->abiertoEl($hoy),
            ],
            // Sube la primera dentro del plazo general, o cuando un docente
            // se lo habilita (tambien para cambiarla). Vencido el plazo sin
            // foto, no esta habilitado en sus examenes.
            'foto' => [
                'puede_subir' => $habilitado
                    || (! $estudiante->conFoto && $plazo?->abiertoEl($hoy) === true),
                'habilitada_hasta' => $habilitado ? $foto['habilitada_hasta'] : null,
                'habilitada_por' => $habilitado ? $foto['habilitada_por'] : null,
                'sin_foto_vencido' => ! $estudiante->conFoto && $plazo !== null && $plazo->estadoEl($hoy) === 'cerrado',
            ],
        ]);
    }

    public function examenes(Request $request, PortalEstudianteGateway $portal): JsonResponse
    {
        return response()->json([
            'data' => $portal->examenes($this->estudiante($request)->id),
            'hoy' => now()->toDateString(),
        ]);
    }

    /**
     * El PNG de su propio codigo QR para ese examen, para guardarlo o
     * compartirlo. 404 si no esta habilitado o el codigo no se emitio.
     */
    public function qr(Request $request, int $examen, CodigosQrDelEstudiante $codigos): JsonResponse|Response
    {
        $imagen = $codigos->png($examen, $this->estudiante($request)->id);

        if ($imagen === null) {
            return response()->json(['message' => 'Sin código QR.'], Response::HTTP_NOT_FOUND);
        }

        return response($imagen->png, Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $imagen->nombre),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function foto(Request $request, PortalEstudianteGateway $portal, AlmacenImagenes $imagenes): JsonResponse|Response
    {
        $ruta = $portal->rutaFoto($this->estudiante($request)->id);
        $jpeg = $ruta === null ? null : $imagenes->leer($ruta);

        if ($jpeg === null) {
            return response()->json(['message' => 'Sin foto.'], Response::HTTP_NOT_FOUND);
        }

        return response($jpeg, Response::HTTP_OK, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * El estudiante escribe su carnet, una sola vez: es obligatorio para
     * que lo habiliten en un examen y despues no se modifica.
     */
    public function carnet(CarnetRequest $request, RegistrarCarnetPropio $registrar): JsonResponse
    {
        try {
            $estudiante = $registrar->execute($this->estudiante($request)->id, $request->string('documento')->toString());
        } catch (CarnetYaRegistradoException $error) {
            return response()->json(['message' => $error->getMessage()], Response::HTTP_CONFLICT);
        } catch (DocumentoEnUsoException) {
            return response()->json([
                'message' => 'Ese carnet ya está registrado.',
                'errors' => ['documento' => ['Ya está registrado · pida ayuda a su docente']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['estudiante' => $estudiante->comoArreglo()], Response::HTTP_CREATED);
    }

    public function subirFoto(FotoRequest $request, SubirFotoPropia $subir): JsonResponse
    {
        try {
            $subir->execute($this->estudiante($request)->id, $request->imagen(), now()->toDateString());
        } catch (FotoYaSubidaException|FueraDePlazoDeFotoException $error) {
            return response()->json(['message' => $error->getMessage()], Response::HTTP_CONFLICT);
        } catch (ImagenNoValidaException $error) {
            return response()->json([
                'message' => $error->getMessage(),
                'errors' => ['foto' => ['Imagen no válida']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['message' => 'Foto registrada.'], Response::HTTP_CREATED);
    }

    private function codigoRechazado(string $mensaje, ?int $restantes): JsonResponse
    {
        return response()->json([
            'message' => 'Código no válido o vencido.',
            'intentos_restantes' => $restantes,
            'errors' => ['codigo' => [$mensaje]],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function sisPendiente(Request $request): ?string
    {
        $sis = $request->hasSession() ? $request->session()->get(self::REGISTRO) : null;

        return is_string($sis) ? $sis : null;
    }

    private function sinRegistro(): JsonResponse
    {
        return response()->json(['message' => 'Verifique primero su correo.'], Response::HTTP_UNAUTHORIZED);
    }

    private function demasiados(int $segundos): JsonResponse
    {
        $minutos = max(1, (int) ceil($segundos / 60));

        return response()->json([
            'message' => sprintf('Demasiados pedidos · espere %d %s', $minutos, $minutos === 1 ? 'minuto' : 'minutos'),
            'reintentar_en' => $segundos,
        ], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $segundos]);
    }

    private function estudiante(Request $request): EstudiantePortalData
    {
        $estudiante = $request->attributes->get(ExigirEstudiante::ATRIBUTO);

        if (! $estudiante instanceof EstudiantePortalData) {
            throw new LogicException('La ruta no pasa por el middleware del estudiante.');
        }

        return $estudiante;
    }

    /**
     * '202100417' → '2021****17'.
     */
    private function enmascarar(string $codigo): string
    {
        $visibles = min(4, intdiv(strlen($codigo), 2));

        return substr($codigo, 0, $visibles).str_repeat('•', strlen($codigo) - $visibles - 2).substr($codigo, -2);
    }
}
