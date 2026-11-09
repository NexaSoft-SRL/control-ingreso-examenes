<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\Contracts\GeneradorImagenQr;
use App\Modules\Ingreso\Application\DTOs\CredencialData;
use App\Modules\Ingreso\Application\DTOs\FiltroCodigosData;

/**
 * Las credenciales emitidas del filtro, listas para la hoja imprimible.
 */
final readonly class ArmarHojaDeCredenciales
{
    public function __construct(
        private CodigoQrGateway $codigos,
        private GeneradorImagenQr $imagenes,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * @return list<array<string, string|int|null>>
     */
    public function execute(int $examenId, FiltroCodigosData $filtro, ?int $usuarioId): array
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        return array_map(
            fn (CredencialData $credencial): array => [
                'facultad' => $credencial->facultad,
                'examen' => $credencial->examen,
                'fecha' => $credencial->fecha,
                'hora' => $credencial->hora,
                'duracion' => $credencial->duracion,
                'estudiante' => $credencial->estudiante,
                'codigo' => $credencial->codigo,
                'aula' => $credencial->aula?->nombre,
                'edificio' => $credencial->aula?->edificio,
                'piso' => $credencial->aula?->piso,
                'imagen' => $this->imagenes->comoUriDeDatos($credencial->token ?? ''),
            ],
            $this->codigos->emitidas($examenId, $filtro),
        );
    }
}
