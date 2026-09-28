<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AmbienteRepository;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Domain\Models\Ambiente;

final readonly class CreateAmbiente
{
    public function __construct(
        private AmbienteRepository $repository,
        private BitacoraGateway $bitacora,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, int $usuarioId): Ambiente
    {
        $ambiente = $this->repository->create($data);

        $id = $ambiente->getKey();

        $this->bitacora->registrar(
            $usuarioId,
            'ambiente.registrar',
            'ambientes',
            is_int($id) ? $id : null,
            null,
        );

        return $ambiente;
    }
}
