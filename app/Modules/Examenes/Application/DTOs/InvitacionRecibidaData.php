<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Una invitacion pendiente del docente: el examen al que lo invitan, el
 * grupo suyo que se sumaria y quien lo invita.
 */
final readonly class InvitacionRecibidaData
{
    /**
     * @param  list<string>  $aulas
     */
    public function __construct(
        public int $id,
        public int $examenId,
        public string $tipo,
        public string $tipoTexto,
        public int $asignaturaId,
        public string $asignaturaCodigo,
        public string $asignaturaNombre,
        public string $fecha,
        public string $hora,
        public int $duracion,
        public array $aulas,
        public int $grupoId,
        public string $grupoCodigo,
        public string $invitadoPor,
    ) {}
}
