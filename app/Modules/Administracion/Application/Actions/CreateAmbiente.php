<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AmbienteRepository;
use App\Modules\Administracion\Domain\Models\Ambiente;

final readonly class CreateAmbiente
{
    public function __construct(
        private AmbienteRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Ambiente
    {
        return $this->repository->create($data);
    }
}
