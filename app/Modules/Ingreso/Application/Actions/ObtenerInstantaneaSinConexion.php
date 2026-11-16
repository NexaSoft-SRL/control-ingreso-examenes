<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\Contracts\SinConexionGateway;
use App\Modules\Ingreso\Application\DTOs\AulaData;
use App\Modules\Ingreso\Application\DTOs\ExamenData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Exceptions\AulaSinExamenException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use Carbon\CarbonImmutable;

/**
 * El puesto descarga con conexion lo que necesita para seguir controlando
 * si internet se cae: los examenes del dia en el aula, sus inscritos con su
 * condicion y las claves de los QR. El servidor anota que ese dispositivo
 * descargo, para saber despues si no sincronizo.
 */
final readonly class ObtenerInstantaneaSinConexion
{
    public function __construct(
        private PuertaGateway $puerta,
        private SinConexionGateway $sinConexion,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws FueraDeAlcanceException
     * @throws AulaSinExamenException
     */
    public function execute(AulaData $aula, OperadorData $operador, string $dispositivo): array
    {
        $delDia = $this->puerta->examenesDelDia($aula->id);

        if (! $this->alcance->atiendeAula($operador, $aula->id, $delDia)) {
            throw FueraDeAlcanceException::aula();
        }

        if ($delDia === []) {
            throw new AulaSinExamenException;
        }

        $ahora = CarbonImmutable::now();
        $ids = array_map(static fn (ExamenData $examen): int => $examen->id, $delDia);

        $this->sinConexion->abrirSesion($dispositivo, $aula->id, $operador, $ahora->format('Y-m-d H:i:s'), $ids);
        $datos = $this->sinConexion->instantanea($ids);

        return [
            'dispositivo' => $dispositivo,
            'instantanea_en' => $ahora->toIso8601String(),
            'servidor_ahora' => $ahora->toIso8601String(),
            'aula' => ['id' => $aula->id, 'nombre' => $aula->nombre],
            'examenes' => array_map(fn (ExamenData $examen): array => $this->examen($examen), $delDia),
            'estudiantes' => $datos['estudiantes'],
            'claves' => (object) $datos['claves'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function examen(ExamenData $examen): array
    {
        $inicio = CarbonImmutable::parse($examen->fecha.' '.$examen->hora);
        $minutos = config('umss.minutos_apertura', 60);

        return [
            'id' => $examen->id,
            'asignatura' => $examen->asignatura,
            'tipo' => $examen->tipo,
            'fecha' => $examen->fecha,
            'hora' => $examen->hora,
            'abre' => $inicio->subMinutes(is_int($minutos) ? $minutos : 60)->toIso8601String(),
            'cierra' => $examen->hasta,
        ];
    }
}
