<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AmbienteRepository;
use App\Modules\Administracion\Domain\Models\Ambiente;

final readonly class DeleteAmbiente
{
    public function __construct(
        private AmbienteRepository $repository,
    ) {}

    public function execute(Ambiente $ambiente): void
    {
        $this->repository->delete($ambiente);
    }
}
