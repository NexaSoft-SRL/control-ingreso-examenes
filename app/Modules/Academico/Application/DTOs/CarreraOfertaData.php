<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\DTOs;

/**
 * Una carrera de la oferta. El codigo puede faltar en el archivo; es anual
 * si alguno de sus niveles es un «AÑO».
 */
final readonly class CarreraOfertaData
{
    /**
     * @param  list<AsignaturaOfertaData>  $asignaturas
     */
    public function __construct(
        public ?string $codigo,
        public string $nombre,
        public bool $anual,
        public array $asignaturas,
    ) {}
}
