<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

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

        $id = $user->getKey();

        $this->registrar(
            'usuario.registrar',
            is_int($id) ? $id : null,
            sprintf('Cuenta creada con el rol %s.', $data['rol']),
        );

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'user' => $user,
        ], 201);
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $cuenta = User::find($user);

        if (! $cuenta instanceof User) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        /** @var array{nombre:string, correo:string, rol:string} $data */
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'correo' => 'required|email|unique:usuarios,correo,'.$user,
            'rol' => 'required|string',
        ]);

        $role = Role::where('name', $data['rol'])->first();

        if (! $role) {
            return response()->json([
                'message' => 'El rol seleccionado no existe.',
            ], 422);
        }

        $cuenta->update([
            'nombre' => $data['nombre'],
            'correo' => $data['correo'],
            'role_id' => $role->id,
        ]);

        $this->registrar('usuario.actualizar', $user, sprintf('Rol asignado: %s.', $data['rol']));

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'user' => $cuenta->refresh(),
        ]);
    }

    /**
     * Activar o desactivar una cuenta. El backlog pide administrar los tipos
     * de usuario: la cuenta no se borra, se deja sin acceso.
     */
    public function estado(Request $request, int $user): JsonResponse
    {
        $cuenta = User::find($user);

        if (! $cuenta instanceof User) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        /** @var array{is_active:bool} $data */
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        // Quedarse fuera del sistema por desactivar la propia cuenta seria
        // irreversible desde la interfaz.
        if ($data['is_active'] === false && Auth::guard('web')->id() === $cuenta->getKey()) {
            return response()->json([
                'message' => 'No puedes desactivar tu propia cuenta.',
            ], 422);
        }

        $cuenta->update(['is_active' => $data['is_active']]);

        $this->registrar(
            $data['is_active'] ? 'usuario.activar' : 'usuario.desactivar',
            $user,
            null,
        );

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'user' => $cuenta->refresh(),
        ]);
    }

    private function registrar(string $operacion, ?int $registroId, ?string $descripcion): void
    {
        $autor = Auth::guard('web')->user()?->getKey();

        $this->bitacora->registrar(
            is_int($autor) ? $autor : null,
            $operacion,
            'usuarios',
            $registroId,
            $descripcion,
        );
    }
}
