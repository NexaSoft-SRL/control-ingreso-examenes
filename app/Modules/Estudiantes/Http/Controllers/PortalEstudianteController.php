<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Controllers;

use App\Modules\Administracion\Application\Contracts\AlmacenImagenes;
use App\Modules\Estudiantes\Application\Actions\EntrarComoEstudiante;
use App\Modules\Estudiantes\Application\Actions\SolicitarCodigoDeAcceso;
use App\Modules\Estudiantes\Application\Actions\SubirFotoPropia;
use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;
use App\Modules\Estudiantes\Application\DTOs\EstudiantePortalData;
use App\Modules\Estudiantes\Domain\Exceptions\CodigoDeAccesoInvalidoException;
use App\Modules\Estudiantes\Domain\Exceptions\FotoYaSubidaException;
use App\Modules\Estudiantes\Domain\Exceptions\FueraDePlazoDeFotoException;
use App\Modules\Estudiantes\Domain\Exceptions\ImagenNoValidaException;
use App\Modules\Estudiantes\Http\Middleware\ExigirEstudiante;
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
    private const FALSO = 'codigo-estudiante-simulado:';

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

        // Para un SIS que no esta en el padron se simula un codigo vigente:
        // al entrar responde igual que uno real con el codigo equivocado.
        if ($codigo === null) {
            // El primer golpe marca el codigo simulado; vence con los minutos
            // del codigo real.
            RateLimiter::clear(self::FALSO.$codigoUniversitario);
            RateLimiter::hit(self::FALSO.$codigoUniversitario, SolicitarCodigoDeAcceso::MINUTOS * 60);
        }
        $dominio = config('umss.dominio_correo_estudiantes');

        $respuesta = [
            'message' => 'Código enviado.',
            'correo' => $this->enmascarar($codigoUniversitario).'@'.(is_string($dominio) ? $dominio : 'est.umss.edu'),
            'minutos' => SolicitarCodigoDeAcceso::MINUTOS,
        ];

        // Sin servidor de correo, en la maquina de desarrollo el codigo se
        // muestra para poder entrar.
        if ($codigo !== null && app()->environment('local') && in_array(config('mail.default'), ['log', 'array'], true)) {
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
        if ($existente !== null && $portal->fueReemplazado($existente->id, $hash)) {
            return $this->codigoRechazado('Código reemplazado · use el del último correo', null);
        }

        try {
            $estudiante = $entrar->execute($sis, $request->codigo());
        } catch (CodigoDeAccesoInvalidoException) {
            RateLimiter::hit($porRed, 600);

            if ($existente === null) {
                $restantes = $this->restantesSimulados($sis);
            } else {
                $restantes = $portal->intentosRestantes($sis, EntrarComoEstudiante::INTENTOS);
            }

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

    public function salir(Request $request): Response
    {
        // `regenerate(true)` destruye la sesion anterior: la cookie vieja no
        // vuelve a abrir el portal en una computadora compartida.
        if ($request->hasSession()) {
            $request->session()->forget(ExigirEstudiante::SESION);
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
        $plazosDeGrupo = $portal->plazosDeGruposDelEstudiante($estudiante->id);
        $plazoGeneralAbierto = $plazo?->abiertoEl($hoy) === true;
        $plazoDeGrupoAbierto = collect($plazosDeGrupo)->contains(
            fn (array $plazoGrupo): bool => $plazoGrupo['desde'] <= $hoy && $hoy <= $plazoGrupo['hasta'],
        );

        return response()->json([
            'estudiante' => $estudiante->comoArreglo(),
            'plazo_foto' => $plazo === null ? null : [
                'desde' => $plazo->desde,
                'hasta' => $plazo->hasta,
                'abierto' => $plazo->abiertoEl($hoy),
            ],
            'plazos_foto_grupo' => $plazosDeGrupo,
            // Sube la primera dentro del plazo, o cuando un docente se lo
            // habilita (tambien para cambiarla).
            'foto' => [
                'puede_subir' => $habilitado || (! $estudiante->conFoto && ($plazoGeneralAbierto || $plazoDeGrupoAbierto)),
                'subida_en' => $foto['subida_en'],
                'habilitada_hasta' => $habilitado ? $foto['habilitada_hasta'] : null,
                'habilitada_por' => $habilitado ? $foto['habilitada_por'] : null,
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

    /**
     * Los intentos de un SIS que no esta en el padron, contados como si
     * tuviera un codigo vigente: nulo si no pidio uno en los ultimos diez
     * minutos («vencido»), igual que un estudiante real.
     */
    private function restantesSimulados(string $sis): ?int
    {
        if (RateLimiter::attempts(self::FALSO.$sis) === 0) {
            return null;
        }

        // El primer golpe fue el pedido: los demas son intentos usados.
        $usados = RateLimiter::hit(self::FALSO.$sis, SolicitarCodigoDeAcceso::MINUTOS * 60) - 1;

        return max(0, EntrarComoEstudiante::INTENTOS - $usados);
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
