<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AmbienteRepository;
use App\Modules\Administracion\Domain\Models\Ambiente;

final readonly class UpdateAmbiente
{
    public function __construct(
        private AmbienteRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Ambiente $ambiente, array $data): Ambiente
    {
        return $this->repository->update($ambiente, $data);
    }
}
