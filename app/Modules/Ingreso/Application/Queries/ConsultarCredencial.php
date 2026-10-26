<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Authorization\AlcanceCodigosQr;
use App\Modules\Ingreso\Application\Contracts\EmisionQrGateway;
use App\Modules\Ingreso\Application\Contracts\GeneradorImagenQr;
use App\Modules\Ingreso\Domain\Exceptions\ExamenAjenoException;

/**
 * La credencial de un habilitado: examen, estudiante, donde rinde y, si
 * el codigo ya se emitio, su imagen.
 */
final readonly class ConsultarCredencial
{
    public function __construct(
        private EmisionQrGateway $codigos,
        private GeneradorImagenQr $imagenes,
        private AlcanceCodigosQr $alcance,
    ) {}

    /**
     * Nulo si el estudiante no esta habilitado en el examen.
     *
     * @return array<string, mixed>|null
     *
     * @throws ExamenAjenoException
     */
    public function execute(int $examenId, int $estudianteId, int $usuarioId): ?array
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        $credencial = $this->codigos->credencial($examenId, $estudianteId);

        if ($credencial === null) {
            return null;
        }

        return [
            'facultad' => $credencial->facultad,
            'examen' => $credencial->examen,
            'fecha' => $credencial->fecha,
            'hora' => $credencial->hora,
            'duracion' => $credencial->duracion,
            'estudiante' => $credencial->estudiante,
            'codigo' => $credencial->codigo,
            'correo' => $credencial->correo,
            'aula' => $credencial->aula === null ? null : [
                'nombre' => $credencial->aula->nombre,
                'edificio' => $credencial->aula->edificio,
                'piso' => $credencial->aula->piso,
                'edificio_id' => $credencial->aula->edificioId,
            ],
            'emitido' => $credencial->contenido !== null,
            'qr' => $credencial->contenido === null
                ? null
                : $this->imagenes->comoUriDeDatos($credencial->contenido),
        ];
    }
}
