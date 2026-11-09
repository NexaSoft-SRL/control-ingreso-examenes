<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\DTOs\AulaData;
use App\Modules\Ingreso\Application\DTOs\ExamenData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;

/**
 * Las aulas que el operador puede controlar hoy. Docente: las de sus
 * examenes; auxiliar: las que atiende; con codigo de aula: la suya.
 */
final readonly class ListarAulasDelPuesto
{
    public function __construct(
        private PuertaGateway $puerta,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, hoy: string, acceso: array<string, mixed>|null}
     */
    public function execute(OperadorData $operador): array
    {
        /** @var array<int, array{aula: AulaData, examenes: list<ExamenData>}> $porAula */
        $porAula = [];

        if ($operador->usuarioId !== null) {
            foreach ($this->puerta->aulasDelDia($operador->usuarioId) as $fila) {
                $porAula[$fila['aula']->id] ??= ['aula' => $fila['aula'], 'examenes' => []];
                $porAula[$fila['aula']->id]['examenes'][] = $fila['examen'];
            }
        } elseif ($operador->aulaId !== null) {
            $aula = $this->puerta->aula($operador->aulaId);

            if ($aula !== null) {
                $porAula[$aula->id] = [
                    'aula' => $aula,
                    'examenes' => $this->puerta->examenesDelDia($aula->id),
                ];
            }
        }

        $data = [];

        foreach ($porAula as $grupo) {
            $abierta = false;
            $examenes = [];

            foreach ($grupo['examenes'] as $examen) {
                $abierta = $abierta || $examen->abierto;
                $examenes[] = [
                    'id' => $examen->id,
                    'texto' => $examen->texto(),
                    'hora' => $examen->hora,
                ];
            }

            $data[] = [
                'aula_id' => $grupo['aula']->id,
                'nombre' => $grupo['aula']->nombre,
                'ubicacion' => $grupo['aula']->ubicacion(),
                'examenes' => $examenes,
                'abierta' => $abierta,
            ];
        }

        return [
            'data' => $data,
            'hoy' => $this->puerta->hoy(),
            'acceso' => $operador->esAcceso() ? [
                'aula_id' => $operador->aulaId,
                'aula' => $operador->aula,
                'examen_id' => $operador->examenId,
                'nombre' => $operador->nombre,
                'hasta' => $operador->hasta,
                // Solo un acceso autorizado llega hasta aqui.
                'estado' => 'autorizado',
            ] : null,
        ];
    }
}
