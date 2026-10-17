<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

final readonly class PersonalAulaData
{
    /**
     * @param  list<int>  $apoyo  IDs de usuarios auxiliares en rol apoyo
     */
    public function __construct(
        public int $aulaId,
        public int $aCargo,
        public array $apoyo = [],
    ) {}
}
