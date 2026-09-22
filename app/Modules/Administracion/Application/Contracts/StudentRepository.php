<?php

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Domain\Models\Student;

interface StudentRepository
{
    public function create(array $data): Student;
    public function all(): array;
    public function update(Student $student, array $data): Student;
    public function delete(Student $student): void;
}
