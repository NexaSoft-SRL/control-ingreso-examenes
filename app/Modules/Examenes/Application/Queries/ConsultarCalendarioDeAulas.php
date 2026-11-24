<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Queries;

use App\Modules\Examenes\Application\Contracts\CalendarioDeAulasGateway;

/**
 * La vista de calendario: que aulas estan ocupadas esos dias y a que hora,
 * para elegir aula antes de registrar el examen.
 */
final readonly class ConsultarCalendarioDeAulas
{
    // Una vista no pasa de dos semanas ni de este numero de aulas.
    public const MAXIMO_DIAS = 14;

    public const MAXIMO_AULAS = 40;

    public function __construct(
        private CalendarioDeAulasGateway $calendario,
    ) {}

    /**
     * @param  list<int>  $aulaIds
     * @return array<int, array{clases: list<array{fecha: string, inicio: string, fin: string, asignatura: string, grupo: string, docente: ?string, auxiliatura: bool}>, examenes: list<array{id: int|null, fecha: string, inicio: string, fin: string, asignatura: string, tipo: string, docente: ?string, propio: bool}>}>
     */
    public function execute(array $aulaIds, ?int $edificioId, string $desde, string $hasta, int $usuarioId): array
    {
        $aulas = $aulaIds;

        if ($edificioId !== null) {
            $aulas = array_values(array_unique([...$aulas, ...$this->calendario->aulasDelEdificio($edificioId)]));
        }

        return $this->calendario->ocupacion(array_slice($aulas, 0, self::MAXIMO_AULAS), $desde, $hasta, $usuarioId);
    }
}
