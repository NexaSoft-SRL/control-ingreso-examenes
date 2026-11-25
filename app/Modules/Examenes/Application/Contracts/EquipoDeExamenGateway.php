<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

/**
 * El equipo de un examen: quien esta a cargo o de apoyo en alguna de sus
 * aulas. Sumar a alguien lo deja de apoyo en todas las aulas.
 */
interface EquipoDeExamenGateway
{
    /**
     * Cuenta activa con rol Auxiliar.
     */
    public function esAuxiliarActivo(int $usuarioId): bool;

    public function tieneAulas(int $examenId): bool;

    /**
     * A cargo o de apoyo en alguna aula del examen.
     */
    public function estaEnElEquipo(int $examenId, int $usuarioId): bool;

    /**
     * A cargo de alguna aula del examen.
     */
    public function estaACargo(int $examenId, int $usuarioId): bool;

    /**
     * Quien sumo a esa persona al apoyo (la primera vez que aparece), o nulo.
     */
    public function agregadoPor(int $examenId, int $usuarioId): ?int;

    /**
     * Lo deja de apoyo en las aulas donde aun no esta, con `$porId` como
     * quien lo suma, y lo asienta en la bitacora.
     *
     * @return bool si se sumo en alguna aula (false si ya estaba en todas)
     */
    public function sumar(int $examenId, int $usuarioId, int $porId): bool;

    /**
     * Lo saca del apoyo de todas las aulas y lo asienta en la bitacora.
     */
    public function quitar(int $examenId, int $usuarioId, int $porId): void;
}
