<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\Contracts\CondicionHabilitacionGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\DTOs\AulaData;
use App\Modules\Ingreso\Application\DTOs\CondicionData;
use App\Modules\Ingreso\Application\DTOs\EstudianteData;
use App\Modules\Ingreso\Application\DTOs\ExamenData;
use App\Modules\Ingreso\Application\DTOs\IngresoData;
use App\Modules\Ingreso\Application\DTOs\NuevaIncidenciaData;
use App\Modules\Ingreso\Application\DTOs\NuevoIngresoData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Application\DTOs\VeredictoData;
use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\MedioIdentificacion;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use App\Modules\Ingreso\Domain\Enums\Veredicto;
use App\Modules\Ingreso\Domain\Exceptions\AulaSinExamenException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use LogicException;

/**
 * La puerta: identifica al estudiante, resuelve a cual de los examenes
 * abiertos del aula pertenece y, si corresponde, asienta el ingreso.
 * Verificar y registrar son una sola operacion.
 */
final readonly class VerificarEnPuerta
{
    private const RELECTURA_SEGUNDOS = 120;

    // Lecturas del mismo ingreso repetido en la misma puerta: una sola
    // incidencia cada diez minutos, no una por lectura.
    private const REPETIDO_MINUTOS = 10;

    // Pasado este margen desde el inicio, el ingreso se marca como tarde.
    private const TOLERANCIA_MINUTOS = 15;

    private const MESES = [
        1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun',
        'jul', 'ago', 'sep', 'oct', 'nov', 'dic',
    ];

    public function __construct(
        private PuertaGateway $puerta,
        private CodigoQrGateway $codigos,
        private CondicionHabilitacionGateway $habilitaciones,
        private IngresoGateway $ingresos,
        private IncidenciaGateway $incidencias,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * Con `$token` se verifica un codigo QR; sin el, un codigo
     * universitario o un documento. Devuelve nulo si el identificador no
     * esta en el padron.
     */
    public function execute(
        AulaData $aula,
        ?string $token,
        ?string $identificador,
        OperadorData $operador,
    ): ?VeredictoData {
        $delDia = $this->puerta->examenesDelDia($aula->id);

        if (! $this->alcance->atiendeAula($operador, $aula->id, $delDia)) {
            throw FueraDeAlcanceException::aula();
        }

        $abiertos = [];

        foreach ($delDia as $examen) {
            if ($examen->abierto) {
                $abiertos[$examen->id] = $examen;
            }
        }

        if ($abiertos === []) {
            throw new AulaSinExamenException;
        }

        $ids = array_keys($abiertos);

        if ($token !== null) {
            $codigo = $this->codigos->resolver($token);
            $estudiante = $codigo === null
                ? null
                : $this->puerta->estudiante($codigo['estudiante_id']);

            if ($codigo === null || $estudiante === null) {
                return $this->noCorresponde($aula, $operador, null, CausaIntento::CodigoInvalido, $ids);
            }

            // Con QR el examen es el del token, no otro del estudiante.
            $medio = MedioIdentificacion::Qr;
            $candidatos = in_array($codigo['examen_id'], $ids, true) ? [$codigo['examen_id']] : [];
        } else {
            $hallado = $this->puerta->estudiantePorIdentificador($identificador ?? '');

            if ($hallado === null) {
                return null;
            }

            $estudiante = $hallado['estudiante'];
            $medio = $hallado['medio'];
            $candidatos = $this->puerta->examenesDondeEstaInscrito($estudiante->id, $ids);
        }

        if ($candidatos === []) {
            return $this->noCorresponde($aula, $operador, $estudiante, CausaIntento::NoInscrito, $ids);
        }

        [$examenId, $previo, $condicion] = $this->elegir($candidatos, $estudiante->id, $aula->id);
        $examen = $abiertos[$examenId];

        if ($previo !== null) {
            return $this->yaIngreso($previo, $aula, $estudiante, $examen, $operador, $medio);
        }

        if ($condicion === null || ! $condicion->habilitado) {
            return $this->noHabilitado($aula, $operador, $estudiante, $examen, $condicion);
        }

        // El aula del reparto es una sugerencia: cualquier puerta del
        // examen admite y el ingreso guarda por donde entro. Asi un aula
        // que a ultima hora no se pudo usar no deja a nadie afuera.
        $sugerida = $condicion->aulaId !== null && $condicion->aulaId !== $aula->id
            ? $this->puerta->aula($condicion->aulaId)
            : null;

        $ingreso = $this->ingresos->registrar(new NuevoIngresoData(
            examenId: $examen->id,
            estudianteId: $estudiante->id,
            aulaId: $aula->id,
            medio: $medio,
            esExcepcion: false,
            operador: $operador,
        ));

        if ($ingreso === null) {
            // Otro puesto lo registro entre la comprobacion y la insercion.
            $ganador = $this->ingresos->deEstudiante($examen->id, $estudiante->id);

            if ($ganador === null) {
                throw new LogicException('El ingreso duplicado no aparece.');
            }

            return $this->yaIngreso($ganador, $aula, $estudiante, $examen);
        }

        $tarde = $this->minutosDeRetraso($examen);

        return new VeredictoData(
            veredicto: Veredicto::Admitido,
            estudiante: $estudiante,
            examen: $examen,
            dato: 'Registrado a las '.$ingreso->horaCorta()
                .($sugerida === null ? '' : ' · su aula sugerida era la '.$sugerida->nombre),
            pie: $tarde > self::TOLERANCIA_MINUTOS ? sprintf('Tarde · %d min después del inicio', $tarde) : null,
            extras: [
                'ingreso' => ['id' => $ingreso->id, 'hora' => $ingreso->horaCorta()],
                'tarde' => $tarde > self::TOLERANCIA_MINUTOS ? $tarde : null,
                'aula_sugerida' => $sugerida?->comoArreglo(),
            ],
        );
    }

    /**
     * Si el estudiante esta en mas de un examen del aula a la vez, se
     * resuelve el que puede admitirlo: sin ingreso, habilitado y con esta
     * aula; despues, el que mas se le acerque.
     *
     * @param  non-empty-list<int>  $candidatos
     * @return array{int, IngresoData|null, CondicionData|null}
     */
    private function elegir(array $candidatos, int $estudianteId, int $aulaId): array
    {
        $mejor = null;
        $menor = PHP_INT_MAX;

        foreach ($candidatos as $examenId) {
            $previo = $this->ingresos->deEstudiante($examenId, $estudianteId);
            $condicion = $this->habilitaciones->condicionDe($examenId, $estudianteId);

            $puesto = match (true) {
                $previo !== null => 3,
                $condicion === null || ! $condicion->habilitado => 2,
                $condicion->aulaId !== $aulaId => 1,
                default => 0,
            };

            if ($puesto < $menor) {
                $menor = $puesto;
                $mejor = [$examenId, $previo, $condicion];
            }
        }

        return $mejor;
    }

    /**
     * Con `$operador` es un intento nuevo con una identificacion que ya
     * entro (un QR prestado, alguien que se hace pasar por otro) y queda
     * como incidencia. Sin el, es la carrera entre dos puestos.
     */
    private function yaIngreso(
        IngresoData $previo,
        AulaData $aula,
        EstudianteData $estudiante,
        ExamenData $examen,
        ?OperadorData $operador = null,
        ?MedioIdentificacion $medio = null,
    ): VeredictoData {
        if ($previo->expulsadoA !== null) {
            return new VeredictoData(
                veredicto: Veredicto::Expulsado,
                estudiante: $estudiante,
                examen: $examen,
                dato: 'Expulsado a las '.$previo->expulsadoA,
            );
        }

        $donde = $previo->aulaId === $aula->id
            ? 'en esta aula'
            : 'en el aula '.$previo->aula;
        $dato = sprintf('Ingresó a las %s %s', $previo->horaCorta(), $donde);
        $registrada = false;

        if (
            $operador !== null
            && ! $this->esRelectura($previo, $aula)
            && ! $this->incidencias->repetidoReciente($previo->id, $aula->id, self::REPETIDO_MINUTOS)
        ) {
            $this->incidencias->registrar(new NuevaIncidenciaData(
                tipo: TipoIncidencia::IngresoRepetido,
                origen: OrigenIncidencia::Sistema,
                detalle: sprintf(
                    '%s ya usado. %s.',
                    $medio === MedioIdentificacion::Qr ? 'Código QR' : 'Código o C.I.',
                    $dato,
                ),
                operador: $operador,
                examenId: $examen->id,
                aulaId: $aula->id,
                estudianteId: $estudiante->id,
                ingresoId: $previo->id,
            ));
            $registrada = true;
        }

        return new VeredictoData(
            veredicto: Veredicto::YaIngreso,
            estudiante: $estudiante,
            examen: $examen,
            dato: $dato,
            pie: $registrada ? 'Incidencia registrada' : null,
        );
    }

    /**
     * Minutos desde el inicio del examen (cero si todavia no empezo). El
     * examen es de hoy: basta la hora del dia.
     */
    private function minutosDeRetraso(ExamenData $examen): int
    {
        [$h, $m] = array_map(intval(...), array_pad(explode(':', $examen->hora), 2, '0'));
        [$ah, $am] = array_map(intval(...), explode(':', now()->format('H:i')));

        return max(0, ($ah * 60 + $am) - ($h * 60 + $m));
    }

    /**
     * La misma credencial leida otra vez en la misma puerta enseguida de
     * admitirla: un error de quien lee, no un intento.
     */
    private function esRelectura(IngresoData $previo, AulaData $aula): bool
    {
        if ($previo->aulaId !== $aula->id) {
            return false;
        }

        // El ingreso es de hoy: basta comparar la hora del dia.
        $segundos = static function (string $hora): int {
            [$h, $m, $s] = array_map(intval(...), array_pad(explode(':', $hora), 3, '0'));

            return $h * 3600 + $m * 60 + $s;
        };

        return abs($segundos(now()->format('H:i:s')) - $segundos($previo->hora)) <= self::RELECTURA_SEGUNDOS;
    }

    private function noHabilitado(
        AulaData $aula,
        OperadorData $operador,
        EstudianteData $estudiante,
        ExamenData $examen,
        ?CondicionData $condicion,
    ): VeredictoData {
        $dato = $condicion === null
            ? 'Sin revisar'
            : ($condicion->motivo ?? 'No habilitado');

        // Leer otra vez al mismo estudiante en la misma puerta no suma
        // intentos: uno cada diez minutos.
        if (! $this->incidencias->intentoReciente($estudiante->id, $aula->id, CausaIntento::NoHabilitado, self::REPETIDO_MINUTOS)) {
            $this->incidencias->registrar(new NuevaIncidenciaData(
                tipo: TipoIncidencia::IntentoNoAutorizado,
                origen: OrigenIncidencia::Sistema,
                detalle: $dato,
                operador: $operador,
                examenId: $examen->id,
                aulaId: $aula->id,
                estudianteId: $estudiante->id,
                causa: CausaIntento::NoHabilitado,
            ));
        }

        return new VeredictoData(
            veredicto: Veredicto::NoHabilitado,
            estudiante: $estudiante,
            examen: $examen,
            dato: $dato,
            pie: $condicion === null ? null : $this->pieDe($condicion),
        );
    }

    /**
     * «Registrado por Omonte Ojalvo Jose Roberto · 6 oct».
     */
    private function pieDe(CondicionData $condicion): ?string
    {
        if ($condicion->registradaPor === null) {
            return null;
        }

        $pie = 'Registrado por '.$condicion->registradaPor;

        if ($condicion->fecha !== null && preg_match('/^\d{4}-(\d{2})-(\d{2})/', $condicion->fecha, $partes) === 1) {
            $pie .= sprintf(' · %d %s', (int) $partes[2], self::MESES[(int) $partes[1]] ?? '');
        }

        return $pie;
    }

    /**
     * @param  list<int>  $abiertos
     */
    private function noCorresponde(
        AulaData $aula,
        OperadorData $operador,
        ?EstudianteData $estudiante,
        CausaIntento $causa,
        array $abiertos,
    ): VeredictoData {
        $correspondiente = $estudiante === null
            ? null
            : $this->puerta->examenCorrespondiente($estudiante->id, $abiertos);

        $dato = $causa === CausaIntento::CodigoInvalido
            ? 'Código QR no válido'
            : sprintf('No inscrito en los exámenes del aula %s', $aula->nombre);

        $repetido = $estudiante !== null
            && $this->incidencias->intentoReciente($estudiante->id, $aula->id, $causa, self::REPETIDO_MINUTOS);

        if (! $repetido) {
            $this->incidencias->registrar(new NuevaIncidenciaData(
                tipo: TipoIncidencia::IntentoNoAutorizado,
                origen: OrigenIncidencia::Sistema,
                detalle: $dato,
                operador: $operador,
                aulaId: $aula->id,
                estudianteId: $estudiante?->id,
                causa: $causa,
                examenCorrespondienteId: $correspondiente['id'] ?? null,
            ));
        }

        return new VeredictoData(
            veredicto: Veredicto::NoCorresponde,
            estudiante: $estudiante,
            dato: $dato,
            pie: 'Intento registrado',
            extras: [
                'examen_correspondiente' => $correspondiente === null ? null : [
                    'texto' => $correspondiente['texto'],
                    'aula' => $correspondiente['aula'],
                ],
            ],
        );
    }
}
