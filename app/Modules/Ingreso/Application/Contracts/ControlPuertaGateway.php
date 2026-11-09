<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

/**
 * Lo que el docente mueve durante el examen: quien atiende cada aula y a
 * que aula va cada estudiante. Todo cambio queda en la bitacora.
 */
interface ControlPuertaGateway
{
    /**
     * Las aulas del examen con quien las atiende.
     *
     * @return list<array{aula_id: int, nombre: string, usa_codigo: bool, personal: list<array{usuario_id: int, nombre: string, rol: string}>}>
     */
    public function aulas(int $examenId): array;

    /**
     * Pasa a una persona de un aula del examen a otra. Si estaba a cargo,
     * el aula que deja queda a cargo de quien hace el cambio. Devuelve
     * `OK`, `AULA_AJENA` (alguna aula no es del examen) o `NO_ESTA` (no
     * atendia el aula de origen).
     */
    public function trasladarPersona(int $examenId, int $usuarioId, int $deAulaId, int $aAulaId, int $autorId): string;

    /**
     * Pasa a otra aula del examen a los habilitados de un aula que todavia
     * no entraron. Devuelve cuantos se movieron, o nulo si alguna aula no
     * es del examen.
     */
    public function trasladarEstudiantes(int $examenId, int $deAulaId, int $aAulaId, int $autorId): ?int;
}
