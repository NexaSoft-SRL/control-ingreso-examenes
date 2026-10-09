<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

/**
 * Edificios y aulas para el mapa del campus y para elegir aula.
 */
interface ConsultaAulasGateway
{
    /**
     * Cada edificio con todas sus aulas y, si se conocen, sus pisos.
     *
     * @return list<array{id: int, clave: string, facultad: string, nombre: string, poligono: list<array{float, float}>, centro: array{float, float}, aulas: list<string>, pisos: list<array{nombre: string, aulas: list<string>}>}>
     */
    public function edificios(?string $claveFacultad): array;

    /**
     * En orden natural por nombre. `$soloUbicadas` deja fuera las aulas
     * sin edificio.
     *
     * @return list<array{id: int, nombre: string, edificio_id: int|null, edificio: string|null, piso: string|null, facultad: string|null}>
     */
    public function aulas(?string $claveFacultad, bool $soloUbicadas): array;
}
