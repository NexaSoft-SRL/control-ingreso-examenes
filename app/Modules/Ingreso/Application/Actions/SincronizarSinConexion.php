<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\Contracts\CondicionHabilitacionGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\Contracts\SinConexionGateway;
use App\Modules\Ingreso\Application\DTOs\AulaData;
use App\Modules\Ingreso\Application\DTOs\EventoSinConexionData;
use App\Modules\Ingreso\Application\DTOs\ExamenData;
use App\Modules\Ingreso\Application\DTOs\NuevaIncidenciaData;
use App\Modules\Ingreso\Application\DTOs\NuevoIngresoData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\MedioIdentificacion;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoIrregularidad;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Un puesto que trabajo sin conexion envia lo que decidio. El servidor
 * aplica cada evento una sola vez, en el orden en que ocurrieron, crea los
 * ingresos y las incidencias que habria creado con conexion y registra como
 * irregularidad todo lo que no cuadra con lo que sabe hoy: dos puestos que
 * admitieron al mismo estudiante, alguien admitido que ya no estaba
 * habilitado, alguien rechazado que si lo estaba, una hora imposible.
 * Las irregularidades las revisa el docente.
 */
final readonly class SincronizarSinConexion
{
    public const RESULTADOS = ['ADMITIDO', 'YA_INGRESO', 'NO_HABILITADO', 'NO_CORRESPONDE', 'CODIGO_INVALIDO'];

    public const MAXIMO_EVENTOS = 2000;

    private const REPETIDO_MINUTOS = 10;

    /** Lo que se tolera entre la hora del puesto y la del servidor, hacia el futuro. */
    private const FUTURO_MINUTOS = 5;

    /** Lo que se tolera antes de abrir y despues de cerrar el examen. */
    private const MARGEN_MINUTOS = 30;

    public function __construct(
        private PuertaGateway $puerta,
        private IngresoGateway $ingresos,
        private CondicionHabilitacionGateway $condiciones,
        private IncidenciaGateway $incidencias,
        private CodigoQrGateway $codigos,
        private SinConexionGateway $sinConexion,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * @param  list<EventoSinConexionData>  $eventos
     * @return array{recibidos: int, aplicados: int, repetidos: int, ingresos: int, incidencias: int, irregularidades: int}
     *
     * @throws FueraDeAlcanceException
     * @throws DatoNoValidoException
     */
    public function execute(AulaData $aula, OperadorData $operador, string $dispositivo, string $instantaneaEn, array $eventos): array
    {
        if (! $this->alcance->atiendeAula($operador, $aula->id, $this->puerta->examenesDelDia($aula->id))) {
            throw FueraDeAlcanceException::aula();
        }

        $instantanea = (new DateTimeImmutable($instantaneaEn))->setTimezone($this->zona())->format('Y-m-d H:i:s');
        $sesionId = $this->sinConexion->sesion($dispositivo, $aula->id, $instantanea);

        if ($sesionId === null) {
            throw new DatoNoValidoException('instantanea', 'Esa instantánea no se descargó en este puesto.');
        }

        if (count($eventos) > self::MAXIMO_EVENTOS) {
            throw new DatoNoValidoException('eventos', sprintf('Máximo %d eventos por envío.', self::MAXIMO_EVENTOS));
        }

        // En el orden en que ocurrieron: el primero que admitio a alguien
        // es el que cuenta.
        usort($eventos, static fn (EventoSinConexionData $a, EventoSinConexionData $b): int => strcmp($a->hora, $b->hora));

        $resumen = ['recibidos' => count($eventos), 'aplicados' => 0, 'repetidos' => 0, 'ingresos' => 0, 'incidencias' => 0, 'irregularidades' => 0];

        foreach ($eventos as $evento) {
            if ($this->sinConexion->eventoAplicado($evento->uid)) {
                $resumen['repetidos']++;

                continue;
            }

            $this->aplicar($aula, $operador, $sesionId, $dispositivo, $evento, $resumen);
            $resumen['aplicados']++;
        }

        $this->sinConexion->cerrarSesion($sesionId, $resumen['aplicados'], DateTimeImmutable::createFromInterface(now())->format('Y-m-d H:i:s'));

        return $resumen;
    }

    /**
     * @param  array{recibidos: int, aplicados: int, repetidos: int, ingresos: int, incidencias: int, irregularidades: int}  $resumen
     */
    private function aplicar(AulaData $aula, OperadorData $operador, int $sesionId, string $dispositivo, EventoSinConexionData $evento, array &$resumen): void
    {
        $hora = (new DateTimeImmutable($evento->hora))->setTimezone($this->zona());
        $resultado = $evento->resultado;
        $medio = MedioIdentificacion::tryFrom($evento->medio ?? '') ?? MedioIdentificacion::Codigo;
        $estudianteId = $evento->estudianteId;
        $examenId = $evento->examenId;
        $token = $evento->token;
        $datos = ['dispositivo' => substr($dispositivo, -6), 'hora_local' => $hora->format(DateTimeInterface::ATOM), 'resultado_local' => $resultado, 'medio' => $medio->value];

        // Un QR que el puesto no conocia (se emitio despues de preparar): el
        // servidor si lo conoce.
        if ($token !== null && $estudianteId === null) {
            $codigo = $this->codigos->resolver($token);

            if ($codigo !== null) {
                $examenId = $codigo['examen_id'];
                $estudianteId = $codigo['estudiante_id'];
                $datos['qr_emitido_despues'] = true;
            }
        }

        $examen = $examenId === null ? null : $this->puerta->examen($examenId);

        // El evento tiene que ser de un examen de esta aula; si no, se descarta.
        if ($examen === null || ! $this->puerta->examenTieneAula($examen->id, $aula->id) || ($estudianteId !== null && $this->puerta->estudiante($estudianteId) === null)) {
            $delDia = $this->puerta->examenesDelDia($aula->id);
            $referencia = $delDia[0]->id ?? null;

            if ($referencia !== null) {
                $this->irregular($resumen, $referencia, $sesionId, TipoIrregularidad::EventoInvalido, sprintf('Un evento «%s» del puesto no corresponde a un examen de esta aula o a un estudiante que exista.', mb_strtolower($resultado)), null, $aula->id, $datos);
            }

            $this->sinConexion->guardarEvento($sesionId, $evento->uid, $hora->format('Y-m-d H:i:s'), $medio->value, $resultado, null, null, null);

            return;
        }

        $ingresoId = null;

        if ($resultado === 'ADMITIDO' && $estudianteId !== null) {
            $ingresoId = $this->admitir($aula, $operador, $sesionId, $examen, $estudianteId, $medio, $hora, $datos, $resumen);
        } elseif ($resultado === 'YA_INGRESO' && $estudianteId !== null) {
            $this->repetido($aula, $operador, $examen, $estudianteId, $medio, $datos, $resumen);
        } else {
            $this->rechazar($aula, $operador, $sesionId, $examen, $estudianteId, $resultado, $evento->detalle ?? '', $datos, $resumen);
        }

        $this->sinConexion->guardarEvento($sesionId, $evento->uid, $hora->format('Y-m-d H:i:s'), $medio->value, $resultado, $examen->id, $estudianteId, $ingresoId);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{recibidos: int, aplicados: int, repetidos: int, ingresos: int, incidencias: int, irregularidades: int}  $resumen
     */
    private function admitir(AulaData $aula, OperadorData $operador, int $sesionId, ExamenData $examen, int $estudianteId, MedioIdentificacion $medio, DateTimeImmutable $hora, array $datos, array &$resumen): ?int
    {
        $estudiante = $this->puerta->estudiante($estudianteId);
        $codigoEstudiante = $estudiante === null ? (string) $estudianteId : $estudiante->codigo;
        $previo = $this->ingresos->deEstudiante($examen->id, $estudianteId);

        if ($previo !== null) {
            $this->irregular(
                $resumen,
                $examen->id,
                $sesionId,
                TipoIrregularidad::IngresoDoble,
                sprintf('Ya figuraba con ingreso a las %s en el aula %s; este puesto lo admitió a las %s en el aula %s.', substr($previo->hora, 0, 5), $previo->aula, $hora->format('H:i'), $aula->nombre),
                $estudianteId,
                $aula->id,
                $datos + ['ingreso_previo' => ['id' => $previo->id, 'aula' => $previo->aula, 'hora' => $previo->hora]],
            );

            return $previo->id;
        }

        [$registradoEn, $dudosa] = $this->horaAplicable($hora, $examen);
        $condicion = $this->condiciones->condicionDe($examen->id, $estudianteId);
        $inscrito = $this->puerta->examenesDondeEstaInscrito($estudianteId, [$examen->id]) !== [];

        $ingreso = $this->ingresos->registrar(new NuevoIngresoData(
            examenId: $examen->id,
            estudianteId: $estudianteId,
            aulaId: $aula->id,
            medio: $medio,
            esExcepcion: false,
            operador: $operador,
            registradoEn: $registradoEn->format('Y-m-d H:i:s'),
        ));

        if ($ingreso === null) {
            // Otro envio lo asento entre la comprobacion y la insercion.
            $ganador = $this->ingresos->deEstudiante($examen->id, $estudianteId);
            $this->irregular($resumen, $examen->id, $sesionId, TipoIrregularidad::IngresoDoble, sprintf('Otro puesto asentó antes el ingreso de %s.', $codigoEstudiante), $estudianteId, $aula->id, $datos);

            return $ganador?->id;
        }

        $resumen['ingresos']++;

        if ($dudosa) {
            $this->irregular($resumen, $examen->id, $sesionId, TipoIrregularidad::HoraDudosa, sprintf('La hora del puesto (%s) no cuadra con el examen o con el servidor; el ingreso se asentó a las %s.', $hora->format('H:i'), $registradoEn->format('H:i')), $estudianteId, $aula->id, $datos);
        }

        if (! $inscrito) {
            $this->irregular($resumen, $examen->id, $sesionId, TipoIrregularidad::NoInscritoAdmitido, sprintf('%s entró al examen sin estar inscrito en sus grupos.', $codigoEstudiante), $estudianteId, $aula->id, $datos);
        } elseif ($condicion === null || ! $condicion->habilitado) {
            $this->irregular($resumen, $examen->id, $sesionId, TipoIrregularidad::AdmitidoNoHabilitado, sprintf('%s entró al examen y hoy no figura habilitado: %s.', $codigoEstudiante, $condicion === null ? 'sin revisar' : ($condicion->motivo ?? 'no habilitado')), $estudianteId, $aula->id, $datos);
        }

        return $ingreso->id;
    }

    /**
     * Una lectura repetida del mismo puesto: ingreso repetido, como con
     * conexion.
     *
     * @param  array<string, mixed>  $datos
     * @param  array{recibidos: int, aplicados: int, repetidos: int, ingresos: int, incidencias: int, irregularidades: int}  $resumen
     */
    private function repetido(AulaData $aula, OperadorData $operador, ExamenData $examen, int $estudianteId, MedioIdentificacion $medio, array $datos, array &$resumen): void
    {
        $previo = $this->ingresos->deEstudiante($examen->id, $estudianteId);

        if ($previo === null || $this->incidencias->repetidoReciente($previo->id, $aula->id, self::REPETIDO_MINUTOS)) {
            return;
        }

        $this->incidencias->registrar(new NuevaIncidenciaData(
            tipo: TipoIncidencia::IngresoRepetido,
            origen: OrigenIncidencia::Sistema,
            detalle: sprintf('%s ya usado (sin conexión). Ingresó a las %s en el aula %s.', $medio === MedioIdentificacion::Qr ? 'Código QR' : 'Código o C.I.', substr($previo->hora, 0, 5), $previo->aula),
            operador: $operador,
            examenId: $examen->id,
            aulaId: $aula->id,
            estudianteId: $estudianteId,
            ingresoId: $previo->id,
        ));
        $resumen['incidencias']++;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{recibidos: int, aplicados: int, repetidos: int, ingresos: int, incidencias: int, irregularidades: int}  $resumen
     */
    private function rechazar(AulaData $aula, OperadorData $operador, int $sesionId, ExamenData $examen, ?int $estudianteId, string $resultado, string $detalle, array $datos, array &$resumen): void
    {
        $causa = match ($resultado) {
            'NO_HABILITADO' => CausaIntento::NoHabilitado,
            'NO_CORRESPONDE' => CausaIntento::NoInscrito,
            default => CausaIntento::CodigoInvalido,
        };

        if ($estudianteId === null || ! $this->incidencias->intentoReciente($estudianteId, $aula->id, $causa, self::REPETIDO_MINUTOS)) {
            $this->incidencias->registrar(new NuevaIncidenciaData(
                tipo: TipoIncidencia::IntentoNoAutorizado,
                origen: OrigenIncidencia::Sistema,
                detalle: trim('Sin conexión. '.$detalle) === 'Sin conexión.' ? 'Intento rechazado sin conexión.' : trim('Sin conexión. '.$detalle),
                operador: $operador,
                examenId: $examen->id,
                aulaId: $aula->id,
                estudianteId: $estudianteId,
                causa: $causa,
            ));
            $resumen['incidencias']++;
        }

        if ($estudianteId === null) {
            return;
        }

        // Lo rechazaron sin conexion, pero hoy esta inscrito, habilitado y no
        // ingreso: pudo quedar sin rendir.
        $condicion = $this->condiciones->condicionDe($examen->id, $estudianteId);
        $inscrito = $this->puerta->examenesDondeEstaInscrito($estudianteId, [$examen->id]) !== [];

        if ($inscrito && $condicion !== null && $condicion->habilitado && $this->ingresos->deEstudiante($examen->id, $estudianteId) === null) {
            $this->irregular($resumen, $examen->id, $sesionId, TipoIrregularidad::RechazadoHabilitado, sprintf('Lo rechazaron sin conexión y hoy figura habilitado y sin ingreso%s.', isset($datos['qr_emitido_despues']) ? ' (su QR se emitió después de preparar el puesto)' : ''), $estudianteId, $aula->id, $datos);
        }
    }

    /**
     * La hora con que se asienta el ingreso y si la del puesto era dudosa:
     * en el futuro, o lejos de la ventana del examen.
     *
     * @return array{DateTimeImmutable, bool}
     */
    private function horaAplicable(DateTimeImmutable $hora, ExamenData $examen): array
    {
        $ahora = DateTimeImmutable::createFromInterface(now());

        if ($hora > $ahora->modify(sprintf('+%d minutes', self::FUTURO_MINUTOS))) {
            return [$ahora, true];
        }

        $minutos = config('umss.minutos_apertura', 60);
        $abre = (new DateTimeImmutable($examen->fecha.' '.$examen->hora, $this->zona()))->modify(sprintf('-%d minutes', is_int($minutos) ? $minutos : 60));
        $cierra = $examen->hasta === '' ? null : (new DateTimeImmutable($examen->hasta))->setTimezone($this->zona());
        $margen = sprintf('%d minutes', self::MARGEN_MINUTOS);

        if ($hora < $abre->modify('-'.$margen) || ($cierra !== null && $hora > $cierra->modify('+'.$margen))) {
            return [$ahora, true];
        }

        return [$hora, false];
    }

    /**
     * @param  array{recibidos: int, aplicados: int, repetidos: int, ingresos: int, incidencias: int, irregularidades: int}  $resumen
     * @param  array<string, mixed>  $datos
     */
    private function irregular(array &$resumen, int $examenId, int $sesionId, TipoIrregularidad $tipo, string $detalle, ?int $estudianteId, ?int $aulaId, array $datos): void
    {
        $this->sinConexion->registrarIrregularidad($examenId, $sesionId, $tipo, $detalle, $estudianteId, $aulaId, $datos);
        $resumen['irregularidades']++;
    }

    private function zona(): DateTimeZone
    {
        $zona = config('app.timezone');

        return new DateTimeZone(is_string($zona) ? $zona : 'UTC');
    }
}
