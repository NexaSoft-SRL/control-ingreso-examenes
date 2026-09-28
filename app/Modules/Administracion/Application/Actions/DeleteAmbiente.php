<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AmbienteRepository;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Domain\Models\Ambiente;

final readonly class DeleteAmbiente
{
    public function __construct(
        private AmbienteRepository $repository,
        private BitacoraGateway $bitacora,
    ) {}

    public function execute(Ambiente $ambiente, int $usuarioId): void
    {
        $id = $ambiente->getKey();

        $this->repository->delete($ambiente);

        $this->bitacora->registrar(
            $usuarioId,
            'ambiente.eliminar',
            'ambientes',
            is_int($id) ? $id : null,
            null,
        );
    }
}
