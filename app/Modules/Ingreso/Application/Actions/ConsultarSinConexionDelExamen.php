<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\Contracts\SinConexionGateway;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use DateTimeImmutable;

/**
 * Lo que el docente revisa de un examen donde se uso el modo sin conexion:
 * los puestos, las irregularidades por resolver y los dispositivos que
 * prepararon el modo y no enviaron su cierre.
 */
final readonly class ConsultarSinConexionDelExamen
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
     */
    public function execute(int $examenId, int $usuarioId): array
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        $examen = $this->puerta->examen($examenId);
        $sesiones = $this->sinConexion->sesionesDelExamen($examenId);
        $irregularidades = $this->sinConexion->irregularidades($examenId);

        // El examen ya termino y el dispositivo nunca envio su cierre: sus
        // eventos, si los hubo, se perdieron o siguen en su celular.
        $terminado = $examen !== null
            && $examen->hasta !== ''
            && (new DateTimeImmutable($examen->hasta))->modify('+30 minutes') < DateTimeImmutable::createFromInterface(now());

        $sinSincronizar = $terminado
            ? array_values(array_filter($sesiones, static fn (array $sesion): bool => $sesion['estado'] === 'DESCARGADA'))
            : [];

        $pendientes = array_values(array_filter($irregularidades, static fn (array $i): bool => $i['estado'] === 'PENDIENTE'));

        return [
            'usado' => $irregularidades !== [] || array_filter($sesiones, static fn (array $s): bool => $s['eventos'] > 0) !== [],
            'examen' => $examen === null ? null : ['id' => $examen->id, 'asignatura' => $examen->asignatura, 'tipo' => $examen->tipo],
            'sesiones' => $sesiones,
            'sin_sincronizar' => $sinSincronizar,
            'irregularidades' => $irregularidades,
            'resumen' => [
                'pendientes' => count($pendientes),
                'altas' => count(array_filter($pendientes, static fn (array $i): bool => $i['gravedad'] === 'ALTA')),
                'resueltas' => count($irregularidades) - count($pendientes),
                'sin_sincronizar' => count($sinSincronizar),
            ],
        ];
    }
}
