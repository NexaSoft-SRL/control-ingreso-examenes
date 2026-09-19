<?php

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListStudents
{
    public function __construct(
        private StudentRepository $repository,
    ) {}

    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }
}
