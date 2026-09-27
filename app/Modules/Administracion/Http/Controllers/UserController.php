<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController
{
    public function store(Request $request): JsonResponse
    {
        /** @var array{nombre:string, correo:string, rol:string} $data */
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'correo' => 'required|email|unique:usuarios,correo',
            'rol' => 'required|string',
        ]);

        $role = Role::where('name', $data['rol'])->first();

        if (! $role) {
            return response()->json([
                'message' => 'El rol seleccionado no existe.',
            ], 422);
        }

        $user = User::create([
            'nombre' => $data['nombre'],
            'correo' => $data['correo'],
            'password' => bcrypt('123456'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'user' => $user,
        ], 201);
    }
}
