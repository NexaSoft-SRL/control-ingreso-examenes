<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Contracts\DetallePuertaGateway;
use App\Modules\Ingreso\Application\DTOs\ResultadoResolucionPuertaData;
use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;

/**
 * Lo que la puerta muestra con cada veredicto, como en el Proyecto Guia:
 * el estudiante (nombre y codigo), el examen (asignatura y tipo) y el dato
 * propio del caso («Registrado a las 08:39», «Ya ingresó a las 08:39 por
 * el aula 691A», el motivo de la inhabilitacion, el aula que le toca...).
 */
final readonly class DetallarVeredictoPuerta
{
    private const MESES = [
        1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun',
        'jul', 'ago', 'sep', 'oct', 'nov', 'dic',
    ];

    public function __construct(
        private DetallePuertaGateway $detalle,
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
    ): array {
        $estudiante = $resultado->candidato !== null ? $resultado->candidato->estudiante : $resultado->estudiante;
        $examenId = $resultado->examenId;
        $examen = $examenId === null ? null : $this->detalle->examen($examenId);
        $habilitacion = $resultado->candidato?->habilitacion;

        $datos = [
            'examen_id' => $examenId,
            'veredicto' => ($veredicto ?? ($resultado->ambiguo ? null : VeredictoIngreso::NoCorresponde))?->value,
            'causa' => $resultado->causa?->value,
            'ambiguo' => $resultado->ambiguo,
            'admitido' => $veredicto?->permiteIngreso() ?? false,
            'estudiante' => $estudiante === null ? null : [
                'id' => $estudiante->id,
                'codigo' => $estudiante->codigoUniversitario,
                'nombre' => $estudiante->nombreCompleto(),
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
            'examen_correspondiente' => null,
        ];

        if ($resultado->ambiguo) {
            $datos['dato'] = 'Inscrito en más de un examen del aula';
            $datos['pie'] = 'Intento registrado';

            return $datos;
        }

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
                $datos['hora'] = $ingreso['hora'] ?? null;
                $datos['dato'] = $ingreso === null ? null : 'Registrado a las '.$ingreso['hora'];
                break;

            case VeredictoIngreso::YaIngreso:
                if ($ingreso !== null) {
                    $datos['hora'] = $ingreso['hora'];
                    $datos['dato'] = sprintf(
                        'Ya ingresó a las %s %s',
                        $ingreso['hora'],
                        $ingreso['aula_id'] === $aulaId || $ingreso['aula'] === null
                            ? 'por esta aula'
                            : 'por el aula '.$ingreso['aula'],
                    );
                }
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

            case VeredictoIngreso::OtraAula:
                $aula = $habilitacion?->aulaId === null
                    ? null
                    : $this->detalle->aula($habilitacion->aulaId);
                $datos['aula_correcta'] = $aula;
                $datos['dato'] = $aula === null ? 'Asignado a otra aula' : 'Su aula es la '.$aula['nombre'];
                break;

            case VeredictoIngreso::NoCorresponde:
                $datos['dato'] = 'Sin aula asignada en este examen';
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
}
