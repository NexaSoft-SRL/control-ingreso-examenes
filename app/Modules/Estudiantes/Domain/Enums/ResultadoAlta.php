<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Enums;

/**
 * Como termino el estudiante que el docente agrego suelto: nuevo en el
 * padron e inscrito, inscrito (ya estaba en el padron), reinscrito (habia
 * sido quitado del grupo) o en conflicto con el padron.
 */
enum ResultadoAlta: string
{
    case Nuevo = 'NUEVO';
    case Inscrito = 'INSCRITO';
    case Reinscrito = 'REINSCRITO';
    case Conflicto = 'CONFLICTO';

    public function mensaje(): string
    {
        return match ($this) {
            self::Nuevo => 'Estudiante agregado al padrón e inscrito.',
            self::Inscrito => 'Estudiante inscrito.',
            self::Reinscrito => 'Estudiante inscrito de nuevo.',
            self::Conflicto => 'Datos distintos a los del padrón: la inscripción queda pendiente de la administración.',
        };
    }
}
