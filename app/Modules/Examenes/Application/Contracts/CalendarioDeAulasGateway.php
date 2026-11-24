<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

/**
 * Lo que ocupa cada aula en un rango de dias: las clases del horario
 * academico y los examenes registrados. Solo lectura.
 */
interface CalendarioDeAulasGateway
{
    /**
     * Las aulas de un edificio, por nombre.
     *
     * @return list<int>
     */
    public function aulasDelEdificio(int $edificioId): array;

    /**
     * Por aula, sus clases (las del horario, repetidas cada dia del rango
     * que cae en un periodo con ese grupo) y sus examenes. `propio` marca los
     * examenes de la cuenta (los que registro o que incluyen un grupo suyo):
     * solo de esos se da el `id`.
     *
     * @param  list<int>  $aulaIds
     * @return array<int, array{clases: list<array{fecha: string, inicio: string, fin: string, asignatura: string, grupo: string, docente: ?string, auxiliatura: bool}>, examenes: list<array{id: int|null, fecha: string, inicio: string, fin: string, asignatura: string, tipo: string, docente: ?string, propio: bool}>}>
     */
    public function ocupacion(array $aulaIds, string $desde, string $hasta, int $usuarioId): array;
}
