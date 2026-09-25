<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\AmbienteRepository;
use App\Modules\Administracion\Domain\Models\Ambiente;

final class EloquentAmbienteRepository implements AmbienteRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Ambiente
    {
        return Ambiente::query()->create($data);
    }

    /**
     * @return list<Ambiente>
     */
    public function all(): array
    {
        /** @var list<Ambiente> $ambientes */
        $ambientes = Ambiente::query()
            ->orderBy('nombre')
            ->get()
            ->all();

        return $ambientes;
    }

    public function findById(int $id): ?Ambiente
    {
        return Ambiente::query()->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ambiente $ambiente, array $data): Ambiente
    {
        $ambiente->fill($data);
        $ambiente->save();

        return $ambiente->refresh();
    }

    public function delete(Ambiente $ambiente): void
    {
        $ambiente->delete();
    }
}
