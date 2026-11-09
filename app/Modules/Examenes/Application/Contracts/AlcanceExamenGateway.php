<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

use App\Modules\Examenes\Application\DTOs\AulaAtendidaData;

/**
 * El alcance de una cuenta sobre un examen: tener el permiso de la pantalla
 * no alcanza, el examen o el aula tienen que ser suyos.
 */
interface AlcanceExamenGateway
{
    /**
     * Docente del examen es quien lo registro o el docente de alguno de
     * sus grupos. Todos lo ven, habilitan, emiten codigos, lo siguen,
     * controlan cualquier aula y atienden incidencias.
     */
    public function esDocenteDelExamen(int $usuarioId, int $examenId): bool;

    /**
     * Solo quien lo registro modifica o elimina el examen.
     */
    public function loRegistro(int $usuarioId, int $examenId): bool;

    /**
     * La cuenta esta a cargo del aula en ese examen o la apoya. No mira la
     * fecha ni si ademas es docente del examen: eso lo combina quien llama.
     */
    public function atiendeAula(int $usuarioId, int $examenId, int $aulaId): bool;

    /**
     * Las aulas que la cuenta atiende, como encargada o de apoyo, en los
     * examenes de ese dia (`AAAA-MM-DD`).
     *
     * @return list<AulaAtendidaData>
     */
    public function aulasQueAtiende(int $usuarioId, string $fecha): array;
}
