<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Domain\Models\Ambiente;

interface AmbienteRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Ambiente;

    /**
     * @return list<Ambiente>
     */
    public function all(): array;

    public function findById(int $id): ?Ambiente;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ambiente $ambiente, array $data): Ambiente;

    public function delete(Ambiente $ambiente): void;
}
