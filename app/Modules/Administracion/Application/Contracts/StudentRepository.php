<?php

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Domain\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StudentRepository
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function findById(int $id): ?Student;
    public function create(array $data): Student;
    public function update(Student $student, array $data): Student;
    public function delete(Student $student): void;
}
