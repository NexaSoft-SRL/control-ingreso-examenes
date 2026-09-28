<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AmbienteRepository;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Domain\Models\Ambiente;

final readonly class UpdateAmbiente
{
    public function __construct(
        private AmbienteRepository $repository,
        private BitacoraGateway $bitacora,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Ambiente $ambiente, array $data, int $usuarioId): Ambiente
    {
        $actualizado = $this->repository->update($ambiente, $data);

        $id = $actualizado->getKey();

        $this->bitacora->registrar(
            $usuarioId,
            'ambiente.actualizar',
            'ambientes',
            is_int($id) ? $id : null,
            null,
        );

        return $actualizado;
    }
}
