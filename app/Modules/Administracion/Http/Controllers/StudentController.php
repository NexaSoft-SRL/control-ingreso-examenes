<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\CreateStudent;
use App\Modules\Administracion\Application\Actions\ListStudents;
use App\Modules\Administracion\Application\Actions\UpdateStudent;
use App\Modules\Administracion\Application\Actions\DeleteStudent;
use App\Modules\Administracion\Domain\Models\Student;
use App\Modules\Administracion\Http\Requests\StoreStudentRequest;
use App\Modules\Administracion\Http\Requests\UpdateStudentRequest;

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

    public function update(UpdateStudentRequest $request, int $id, UpdateStudent $action)
    {
        $validated = $request->validated();

        
        $domainStudent = new Student(
            id: $id,
            nombre: $validated['nombre'],
            apellido: $validated['apellido'],
            ci: $validated['ci'],
            correo: $validated['correo'],
            activo: $validated['activo'],
        );

        return response()->json(
            $action->execute($domainStudent, $validated)
        );
    }

    public function destroy(int $id, DeleteStudent $action)
    {
         
        $domainStudent = new Student(
            id: $id,
            nombre: '',
            apellido: '',
            ci: '',
            correo: '',
            activo: true,
        );

        $action->execute($domainStudent);

        return response()->json(null, 204);
    }
}
