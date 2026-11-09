<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Domain\Rules\NombreDeUsuario;

/**
 * Cara publica de la accion `ProponerUsuario` y del formato del usuario: las
 * acciones y reglas de un modulo son privadas, y `Academico` y `Examenes`
 * necesitan la propuesta y validar el usuario que se escribe.
 */
interface ProponedorUsuario
{
    /**
     * Nombre y apellido con un espacio («jose soruco»); tambien punto o
     * guion entre las partes.
     */
    public const FORMATO = NombreDeUsuario::FORMATO;

    /**
     * Usuario libre propuesto para un nombre escrito como en la oferta
     * («Paterno Materno Nombres»): `nombre apellido`, con sufijo numerico
     * si ya esta tomado.
     */
    public function proponer(string $nombreCompleto): string;

    /**
     * Como se guarda un usuario escrito: minusculas, sin tildes y con un
     * solo espacio entre palabras.
     */
    public function normalizar(string $usuario): string;
}
