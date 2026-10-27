<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Estudiantes\Application\Contracts\FotosDeEstudiantes;
use App\Modules\Ingreso\Application\Actions\RegistrarIngreso;
use App\Modules\Ingreso\Application\Contracts\DetallePuertaGateway;
use App\Modules\Ingreso\Application\DTOs\ResultadoResolucionPuertaData;
use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
use App\Modules\Ingreso\Domain\Rules\EvaluarIngreso;

/**
 * Lo que la puerta muestra con cada veredicto, como en el Proyecto Guia:
 * el estudiante (nombre y codigo), el examen (asignatura y tipo) y el dato
 * propio del caso («Registrado a las 08:39 · su aula sugerida era la
 * 691A», «Ingresó a las 08:39 en el aula 691A», el motivo de la
 * inhabilitacion...).
 */
final readonly class DetallarVeredictoPuerta
{
    private const MESES = [
        1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun',
        'jul', 'ago', 'sep', 'oct', 'nov', 'dic',
    ];

    public function __construct(
        private DetallePuertaGateway $detalle,
        private FotosDeEstudiantes $fotos,
        private EvaluarIngreso $reglas,
    ) {}

    /**
     * @param  list<int>  $examenesDelAula
     * @return array<string, mixed>
     */
    public function detallar(
        ResultadoResolucionPuertaData $resultado,
        ?VeredictoIngreso $veredicto,
        int $aulaId,
        string $fecha,
        string $tipo,
        string $identificador,
        array $examenesDelAula = [],
        bool $incidenciaRegistrada = false,
    ): array {
        $estudiante = $resultado->candidato !== null ? $resultado->candidato->estudiante : $resultado->estudiante;
        $examenId = $resultado->examenId;
        $examen = $examenId === null ? null : $this->detalle->examen($examenId);
        $habilitacion = $resultado->candidato?->habilitacion;

        $datos = [
            'examen_id' => $examenId,
            'veredicto' => ($veredicto ?? VeredictoIngreso::NoCorresponde)->value,
            'causa' => $resultado->causa?->value,
            'admitido' => $veredicto?->permiteIngreso() ?? false,
            'estudiante' => $estudiante === null ? null : [
                'id' => $estudiante->id,
                'codigo' => $estudiante->codigoUniversitario,
                'nombre' => $estudiante->nombreCompleto(),
                // La foto va dentro del veredicto: quien controla compara la
                // cara sin otra peticion.
                'foto' => $this->foto($estudiante->id),
            ],
            'examen' => $examen === null ? null : [
                'id' => $examen['id'],
                'asignatura' => $examen['asignatura'],
                'tipo' => $examen['tipo'],
            ],
            'codigo' => $estudiante?->codigoUniversitario,
            'nombre' => $estudiante?->nombreCompleto(),
            'registrado_en' => null,
            'hora' => null,
            'ingreso' => null,
            'dato' => null,
            'pie' => null,
            'aula_correcta' => null,
            'aula_sugerida' => null,
            'tarde' => null,
            'examen_correspondiente' => null,
        ];

        if ($resultado->causa !== null) {
            return $this->sinExamen($datos, $resultado, $aulaId, $fecha, $tipo, $identificador, $examenesDelAula);
        }

        if ($examenId === null || $estudiante === null || $veredicto === null) {
            return $datos;
        }

        $ingreso = $this->detalle->ingresoDe($examenId, $estudiante->id);

        if ($ingreso !== null) {
            $datos['ingreso'] = ['id' => $ingreso['id'], 'hora' => $ingreso['hora']];
        }

        switch ($veredicto) {
            case VeredictoIngreso::Admitido:
                // El aula del reparto es una sugerencia: entro por esta.
                $sugerida = $habilitacion?->aulaId !== null && $habilitacion->aulaId !== $aulaId
                    ? $this->detalle->aula($habilitacion->aulaId)
                    : null;
                $tarde = $ingreso === null || $examen === null
                    ? null
                    : $this->reglas->minutosTarde(self::minutos($ingreso['hora']) - self::minutos($examen['hora']));

                $datos['hora'] = $ingreso['hora'] ?? null;
                $datos['dato'] = $ingreso === null ? null : 'Registrado a las '.$ingreso['hora']
                    .($sugerida === null ? '' : ' · su aula sugerida era la '.$sugerida['nombre']);
                $datos['aula_sugerida'] = $sugerida;
                $datos['tarde'] = $tarde;
                $datos['pie'] = $tarde === null ? null : sprintf('Tarde · %d min después del inicio', $tarde);
                break;

            case VeredictoIngreso::YaIngreso:
                if ($ingreso !== null) {
                    $datos['hora'] = $ingreso['hora'];
                    $datos['dato'] = RegistrarIngreso::yaIngresoTexto(
                        $ingreso['hora'],
                        $ingreso['aula_id'] === $aulaId || $ingreso['aula'] === null ? null : $ingreso['aula'],
                    );
                }
                $datos['pie'] = $incidenciaRegistrada ? 'Incidencia registrada' : null;
                break;

            case VeredictoIngreso::Expulsado:
                if ($ingreso !== null) {
                    $datos['hora'] = $ingreso['expulsado_a'];
                    $datos['dato'] = $ingreso['expulsado_a'] === null
                        ? 'Expulsado'
                        : 'Expulsado a las '.$ingreso['expulsado_a'];
                }
                break;

            case VeredictoIngreso::NoHabilitado:
                $datos['dato'] = $habilitacion === null
                    ? 'Sin revisar'
                    : (trim((string) $habilitacion->motivo) ?: 'No habilitado');
                $datos['pie'] = $habilitacion === null ? null : $this->pieDe(
                    $habilitacion->registradaPor,
                    $habilitacion->registradaEn,
                );
                break;

            case VeredictoIngreso::NoCorresponde:
                $datos['dato'] = 'No inscrito en este examen';
                break;
        }

        return $datos;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<int>  $examenesDelAula
     * @return array<string, mixed>
     */
    private function sinExamen(
        array $datos,
        ResultadoResolucionPuertaData $resultado,
        int $aulaId,
        string $fecha,
        string $tipo,
        string $identificador,
        array $examenesDelAula,
    ): array {
        $datos['pie'] = 'Intento registrado';

        switch ($resultado->causa) {
            case CausaIntento::QrNoValido:
                $datos['dato'] = 'Código QR no válido';
                break;

            case CausaIntento::NoRegistrado:
                $datos['dato'] = $tipo === 'codigo'
                    ? trim($identificador).' · sin registro en el padrón'
                    : 'Sin registro en el padrón';
                break;

            case CausaIntento::NoInscrito:
                $aula = $this->detalle->aula($aulaId);
                $datos['dato'] = sprintf(
                    'No inscrito en los exámenes del aula %s',
                    $aula['nombre'] ?? '',
                );
                $datos['examen_correspondiente'] = $resultado->estudiante === null
                    ? null
                    : $this->detalle->examenCorrespondiente(
                        $resultado->estudiante->id,
                        $fecha,
                        $examenesDelAula,
                    );
                break;

            default:
                break;
        }

        return $datos;
    }

    private function foto(int $estudianteId): ?string
    {
        $jpeg = $this->fotos->foto($estudianteId);

        return $jpeg === null ? null : 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }

    // «Registrado por Omonte Ojalvo Jose Roberto · 6 oct».
    private function pieDe(?string $por, ?string $fecha): ?string
    {
        if ($por === null || trim($por) === '') {
            return null;
        }

        $pie = 'Registrado por '.$por;

        if ($fecha !== null && preg_match('/^\d{4}-(\d{2})-(\d{2})/', $fecha, $partes) === 1) {
            $pie .= sprintf(' · %d %s', (int) $partes[2], self::MESES[(int) $partes[1]] ?? '');
        }

        return $pie;
    }

    // '08:39' → 519.
    private static function minutos(string $hora): int
    {
        [$h, $m] = array_map(intval(...), array_pad(explode(':', $hora), 2, '0'));

        return $h * 60 + $m;
    }
}
