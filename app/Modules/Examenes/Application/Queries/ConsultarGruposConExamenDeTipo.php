<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Queries;

use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use App\Modules\Examenes\Domain\Rules\AvanceDeExamen;

/**
 * Aviso del asistente, en el paso de grupos: cuales de los grupos elegidos ya
 * tienen otro examen de ese tipo. El registro lo rechazaria igual; asi se dice
 * antes de llegar al final.
 */
final readonly class ConsultarGruposConExamenDeTipo
{
    public function __construct(
        private ConsultaExamenGateway $consulta,
    ) {}

    /**
     * @param  list<int>  $grupoIds
     * @return list<array{grupo_id: int, codigo: string, mensaje: string}>
     */
    public function execute(array $grupoIds, TipoExamen $tipo, ?int $exceptoExamenId): array
    {
        $tipoConArticulo = AvanceDeExamen::tipoConArticulo($tipo);

        return array_map(
            static fn (array $grupo): array => [
                'grupo_id' => $grupo['id'],
                'codigo' => $grupo['codigo'],
                'mensaje' => "El grupo {$grupo['codigo']} ya tiene {$tipoConArticulo}.",
            ],
            $this->consulta->gruposOcupadosPorTipo($grupoIds, $tipo->value, $exceptoExamenId),
        );
    }
}
