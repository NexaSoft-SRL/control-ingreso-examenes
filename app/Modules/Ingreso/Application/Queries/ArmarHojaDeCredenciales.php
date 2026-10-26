<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Authorization\AlcanceCodigosQr;
use App\Modules\Ingreso\Application\Contracts\EmisionQrGateway;
use App\Modules\Ingreso\Application\Contracts\GeneradorImagenQr;
use App\Modules\Ingreso\Application\DTOs\CredencialData;
use App\Modules\Ingreso\Application\DTOs\FiltroCodigosData;
use App\Modules\Ingreso\Domain\Exceptions\ExamenAjenoException;

/**
 * Las credenciales emitidas del filtro, listas para la hoja imprimible.
 */
final readonly class ArmarHojaDeCredenciales
{
    public function __construct(
        private EmisionQrGateway $codigos,
        private GeneradorImagenQr $imagenes,
        private AlcanceCodigosQr $alcance,
    ) {}

    /**
     * @return list<array<string, string|int|null>>
     *
     * @throws ExamenAjenoException
     */
    public function execute(int $examenId, FiltroCodigosData $filtro, int $usuarioId): array
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        return array_map(
            fn (CredencialData $credencial): array => [
                'facultad' => $credencial->facultad,
                'examen' => $credencial->examen,
                'fecha' => $credencial->fechaTexto,
                'hora' => $credencial->hora,
                'duracion' => $credencial->duracion,
                'estudiante' => $credencial->estudiante,
                'codigo' => $credencial->codigo,
                'aula' => $credencial->aula?->nombre,
                'ubicacion' => $credencial->aula?->ubicacion(),
                'imagen' => $this->imagenes->comoUriDeDatos($credencial->contenido ?? ''),
            ],
            $this->codigos->emitidas($examenId, $filtro),
        );
    }
}
