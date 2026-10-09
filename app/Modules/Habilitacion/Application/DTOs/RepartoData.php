<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

/**
 * El resultado de «Repartir».
 */
final readonly class RepartoData
{
    /**
     * @param  list<AulaRepartoData>  $porAula
     */
    public function __construct(
        public int $repartidos,
        /** Aulas que recibieron al menos un estudiante en este reparto. */
        public int $aulasUsadas,
        public array $porAula,
    ) {}
}
