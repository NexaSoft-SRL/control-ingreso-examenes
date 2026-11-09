<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\DTOs\AulaData;
use App\Modules\Ingreso\Application\DTOs\ExamenData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;

/**
 * La cabecera y los ingresos de la puerta de un aula. Se consulta por
 * sondeo: trae la hora del servidor.
 */
final readonly class ConsultarPuerta
{
    private const RECIENTES = 5;

    public function __construct(
        private PuertaGateway $puerta,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(AulaData $aula, OperadorData $operador, bool $todos, ?string $buscar): array
    {
        $delDia = $this->puerta->examenesDelDia($aula->id);

        if (! $this->alcance->atiendeAula($operador, $aula->id, $delDia)) {
            throw FueraDeAlcanceException::aula();
        }

        $abiertos = array_values(array_filter(
            $delDia,
            static fn (ExamenData $examen): bool => $examen->abierto,
        ));

        // Sin ninguno abierto se muestran los de hoy, con la puerta cerrada.
        $visibles = $abiertos === [] ? $delDia : $abiertos;

        $ingresos = $this->puerta->ingresosDelAula(
            $aula->id,
            array_map(static fn (ExamenData $examen): int => $examen->id, $visibles),
            $todos ? null : self::RECIENTES,
            $todos ? $buscar : null,
        );

        return [
            'aula' => [
                'id' => $aula->id,
                'nombre' => $aula->nombre,
                'ubicacion' => $aula->ubicacion(),
                'edificio_id' => $aula->edificioId,
            ],
            'hora_servidor' => $this->puerta->horaServidor(),
            'abierta' => $abiertos !== [],
            'examenes' => array_map(
                static fn (ExamenData $examen): array => [
                    'id' => $examen->id,
                    'asignatura' => $examen->asignatura,
                    'tipo' => $examen->tipo,
                    'hora' => $examen->hora,
                    'ingresados' => $examen->ingresados,
                    'asignados' => $examen->asignados,
                    'faltan' => $examen->faltan,
                    'normas' => $examen->normas,
                    'normas_marcadas' => $examen->normasMarcadas,
                ],
                $visibles,
            ),
            'ingresos' => $ingresos['ingresos'],
            'total_ingresos' => $ingresos['total'],
        ];
    }
}
