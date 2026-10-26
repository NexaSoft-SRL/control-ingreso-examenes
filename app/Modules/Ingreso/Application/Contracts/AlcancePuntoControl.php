<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

/**
 * La regla unica de «puede operar la puerta de esta aula» (HU-19). La usan
 * la puerta y cualquier pantalla que ofrezca ir a ella (HU-22,
 * «Controlar»). Puede operar quien esta asignado al aula del examen (a
 * cargo o apoyo, `examen_aula_personal`) y los docentes del examen (quien
 * lo registro y los docentes de sus grupos), en todas sus aulas.
 */
interface AlcancePuntoControl
{
    public function puedeOperar(
        int $usuarioId,
        int $examenId,
        int $aulaId,
    ): bool;

    /**
     * Las aulas del examen cuya puerta puede operar la persona, con la
     * misma regla que `puedeOperar`.
     *
     * @return list<int>
     */
    public function aulasOperables(
        int $usuarioId,
        int $examenId,
    ): array;
}
