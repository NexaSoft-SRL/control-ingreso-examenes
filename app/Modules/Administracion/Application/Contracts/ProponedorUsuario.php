<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

/**
 * Cara publica de la accion `ProponerUsuario`: las acciones de un modulo
 * son privadas, y `Academico` y `Examenes` necesitan la propuesta.
 */
interface ProponedorUsuario
{
    /**
     * Usuario libre propuesto para un nombre escrito como en la oferta
     * («Paterno Materno Nombres»): `nombre.apellido`, con sufijo numerico
     * si ya esta tomado.
     */
    public function proponer(string $nombreCompleto): string;
}
