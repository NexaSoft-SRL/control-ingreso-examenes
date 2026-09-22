<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\CreateStudent;
use App\Modules\Administracion\Application\Actions\ListStudents;
use App\Modules\Administracion\Application\Actions\UpdateStudent;
use App\Modules\Administracion\Application\Actions\DeleteStudent;
use App\Modules\Administracion\Domain\Models\Student;
use App\Modules\Administracion\Http\Requests\StoreStudentRequest;
use App\Modules\Administracion\Http\Requests\UpdateStudentRequest;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentStudent;

class StudentController 
{
    public function index(ListStudents $action)
    {
        return response()->json($action->execute());
    }

    public function store(StoreStudentRequest $request, CreateStudent $action)
    {
        return response()->json($action->execute($request->validated()), 201);
    }

    public function update(UpdateStudentRequest $request, EloquentStudent $student, UpdateStudent $action)
{
    return response()->json($action->execute($student, $request->validated()));
}

 public function destroy(EloquentStudent $student, DeleteStudent $action)
{
    $action->execute($student);
    return response()->json(null, 204);
}
}
