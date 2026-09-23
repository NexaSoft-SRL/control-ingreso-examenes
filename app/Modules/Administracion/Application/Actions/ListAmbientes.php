<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AmbienteRepository;
use App\Modules\Administracion\Domain\Models\Ambiente;

final readonly class ListAmbientes
{
    public function __construct(
        private AmbienteRepository $repository,
    ) {}

    /**
     * @return list<Ambiente>
     */
    public function execute(): array
    {
        return $this->repository->all();
    }
}
