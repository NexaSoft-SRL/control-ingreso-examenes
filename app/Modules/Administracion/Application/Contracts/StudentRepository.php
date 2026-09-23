<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Domain\Models\Student;

interface StudentRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Student;

    /**
     * @return list<Student>
     */
    public function all(): array;

    public function findById(int $id): ?Student;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, array $data): Student;

    public function delete(Student $student): void;
}
