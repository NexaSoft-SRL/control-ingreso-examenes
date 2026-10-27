<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Examenes\Application\Contracts\AccesoCodigoAulaGateway;
use App\Modules\Ingreso\Application\Authorization\AlcancePuerta;
use App\Modules\Ingreso\Application\Contracts\ControlPuertaGateway;

/**
 * Quien controla cada aula del examen, en vivo: el personal asignado (y si
 * esta en la puerta), quien mas la esta usando y los accesos con codigo,
 * pendientes de autorizar o ya autorizados. Lo ve el docente.
 */
final readonly class ConsultarControlDelExamen
{
    // Quien uso la puerta en estos minutos cuenta como presente.
    public const MINUTOS_EN_LINEA = 2;

    public function __construct(
        private AlcancePuerta $alcance,
        private ControlPuertaGateway $control,
        private AccesoCodigoAulaGateway $accesos,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{pendientes: int, minutos_en_linea: int}}
     */
    public function ejecutar(int $examenId, int $usuarioId): array
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        $presentes = [];

        foreach ($this->control->presentes($examenId, self::MINUTOS_EN_LINEA) as $presente) {
            $presentes[$presente['aula_id']][$presente['usuario_id']] = $presente;
        }

        $accesos = [];

        foreach ($this->accesos->delExamen($examenId) as $acceso) {
            $accesos[$acceso['aula_id']][] = $acceso;
        }

        $aulas = [];
        $pendientes = 0;

        foreach ($this->control->aulas($examenId) as $aula) {
            $deAula = $presentes[$aula['aula_id']] ?? [];
            $personal = [];

            foreach ($aula['personal'] as $persona) {
                $personal[] = [
                    ...$persona,
                    'visto_en' => $deAula[$persona['usuario_id']]['visto_en'] ?? null,
                ];
                unset($deAula[$persona['usuario_id']]);
            }

            // Alguien con cuenta que no estaba asignado (otro docente del
            // examen, por ejemplo) tambien esta controlando.
            foreach ($deAula as $presente) {
                $personal[] = [
                    'usuario_id' => $presente['usuario_id'],
                    'nombre' => $presente['nombre'],
                    'rol' => 'otro',
                    'visto_en' => $presente['visto_en'],
                ];
            }

            $conCodigo = $accesos[$aula['aula_id']] ?? [];
            $pendientes += count(array_filter($conCodigo, static fn (array $a): bool => $a['estado'] === 'pendiente'));

            $aulas[] = [
                'aula_id' => $aula['aula_id'],
                'nombre' => $aula['nombre'],
                'usa_codigo' => $aula['usa_codigo'],
                'personal' => $personal,
                'accesos' => $conCodigo,
            ];
        }

        return [
            'data' => $aulas,
            'meta' => ['pendientes' => $pendientes, 'minutos_en_linea' => self::MINUTOS_EN_LINEA],
        ];
    }
}
