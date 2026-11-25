<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Lo que hace falta de una invitacion para decidir si se puede responder.
 */
final readonly class InvitacionData
{
    public function __construct(
        public int $id,
        public int $examenId,
        public int $grupoId,
        public string $grupoCodigo,
        public string $estado,
        public string $tipo,
        /** La cuenta del docente del grupo; nulo si no tiene. */
        public ?int $docenteUsuarioId,
    ) {}
}
