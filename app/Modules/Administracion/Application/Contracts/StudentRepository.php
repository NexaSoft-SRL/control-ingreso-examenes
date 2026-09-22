<?php

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Infrastructure\Persistence\EloquentStudent;

interface StudentRepository
{
    public function create(array $data): EloquentStudent;
    public function all();
    public function update(EloquentStudent $student, array $data): EloquentStudent;
    public function delete(EloquentStudent $student): void;
}
