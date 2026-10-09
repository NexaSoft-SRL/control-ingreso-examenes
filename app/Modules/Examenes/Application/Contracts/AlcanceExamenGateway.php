<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

/**
 * El alcance de una cuenta sobre un examen: tener el permiso de la pantalla
 * no alcanza, el examen tiene que ser suyo.
 */
interface AlcanceExamenGateway
{
    /**
     * Docente del examen es quien lo registro o el docente de alguno de
     * sus grupos. Todos lo ven y habilitan a sus inscritos.
     */
    public function esDocenteDelExamen(int $usuarioId, int $examenId): bool;

    /**
     * Solo quien lo registro modifica o elimina el examen.
     */
    public function loRegistro(int $usuarioId, int $examenId): bool;
}
