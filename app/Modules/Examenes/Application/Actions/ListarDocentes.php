<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\DocenteGateway;
use App\Modules\Examenes\Domain\Models\Docente;

final readonly class ListarDocentes
{
    public function __construct(
        private DocenteGateway $gateway,
    ) {}

    /**
     * @return list<Docente>
     */
    public function execute(): array
    {
        return $this->gateway->listarActivos();
    }
}
