<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'correo' => 'required|email|unique:usuarios,correo',
            'rol' => 'required|string',
        ]);

        $role = Role::where('name', $data['rol'])->first();

        if (!$role) {
            return response()->json([
                'message' => 'El rol seleccionado no existe.',
            ], 422);
        }

        $user = User::create([
            'nombre' => $data['nombre'],
            'correo' => $data['correo'],
            'password' => Hash::make('123456'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'user' => $user->load('role'),
        ], 201);
    }
}