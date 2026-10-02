<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Domain\Models\Examen;

final readonly class ListarExamenes
{
    public function __construct(
        private ExamenGateway $gateway,
    ) {}

    /**
     * @return list<Examen>
     */
    public function execute(): array
    {
        return $this->gateway->listar();
    }
}
