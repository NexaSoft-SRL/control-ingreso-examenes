<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

/**
 * El alcance de un docente sobre la oferta: tener el permiso de la pantalla
 * no alcanza, el grupo tiene que ser suyo.
 */
interface AlcanceDocenteGateway
{
    /**
     * El docente unido a la cuenta; null si la cuenta no es de un docente.
     */
    public function docenteDeUsuario(int $usuarioId): ?int;

    public function esGrupoDelDocente(int $usuarioId, int $grupoId): bool;
}
