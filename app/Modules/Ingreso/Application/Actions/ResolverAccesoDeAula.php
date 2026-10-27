<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AccesoCodigoAulaGateway;
use App\Modules\Ingreso\Application\Authorization\AlcancePuerta;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;

/**
 * El docente decide sobre un acceso con codigo de aula: lo autoriza (en
 * el aula que eligio), lo rechaza, lo corta o lo pasa a otra aula.
 */
final readonly class ResolverAccesoDeAula
{
    private const ESTADOS = [
        'autorizar' => 'AUTORIZADO',
        'trasladar' => 'AUTORIZADO',
        'rechazar' => 'RECHAZADO',
        'cortar' => 'CORTADO',
    ];

    public function __construct(
        private AlcancePuerta $alcance,
        private AccesoCodigoAulaGateway $accesos,
    ) {}

    /**
     * Devuelve el estado que quedo, o nulo si el acceso no es del examen.
     *
     * @throws DatoNoValidoException
     */
    public function ejecutar(int $examenId, int $accesoId, string $accion, int $usuarioId, ?int $aulaId = null): ?string
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        $estado = $this->accesos->resolver($accesoId, $examenId, self::ESTADOS[$accion], $usuarioId, $aulaId);

        if ($estado === 'AULA_AJENA') {
            throw new DatoNoValidoException('aula_id', 'El aula no es de este examen.');
        }

        return $estado;
    }
}
