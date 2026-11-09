<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * La asistencia de un aula de un examen.
 */
final readonly class AsistenciaDeAulaData
{
    public function __construct(
        public ExamenSeguidoData $examen,
        public AulaSeguidaData $aula,
        public PaginaAsistenciaData $pagina,
    ) {}
}
