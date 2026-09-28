<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Domain\Models\Permission;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleController
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function getUsers(): JsonResponse
    {
        $users = User::with('role')->orderBy('nombre')->get();

        // La pantalla espera el nombre del rol en "rol": con la relacion
        // cruda la columna salia vacia.
        $data = $users->map(static fn (User $user): array => [
            'id' => $user->getKey(),
            'nombre' => $user->nombre,
            'correo' => $user->correo,
            'rol' => $user->role?->name,
            'is_active' => $user->is_active,
        ]);

        return response()->json($data);
    }

    public function getRoles(): JsonResponse
    {
        $roles = Role::with('permissions')->orderBy('name')->get();

        return response()->json($roles);
    }

    public function getPermissions(): JsonResponse
    {
        $permissions = Permission::query()->orderBy('screen_name')->get();

        return response()->json($permissions);
    }

    /**
     * El backlog pide que el conjunto de permisos de cada rol sea
     * modificable: hasta ahora solo lo fijaba el seeder.
     */
    public function updatePermissions(Request $request, int $role): JsonResponse
    {
        $encontrado = Role::find($role);

        if (! $encontrado instanceof Role) {
            return response()->json([
                'message' => 'Rol no encontrado.',
            ], 404);
        }

        /** @var array{permisos: list<int>} $data */
        $data = $request->validate([
            'permisos' => 'present|array',
            'permisos.*' => 'integer|exists:permissions,id',
        ]);

        $encontrado->permissions()->sync($data['permisos']);

        $autor = Auth::guard('web')->user()?->getKey();

        $this->bitacora->registrar(
            is_int($autor) ? $autor : null,
            'rol.permisos',
            'roles',
            $role,
            sprintf(
                'El rol %s quedó con %d permisos.',
                (string) $encontrado->name,
                count($data['permisos']),
            ),
        );

        $encontrado->load('permissions');

        return response()->json([
            'message' => 'Permisos actualizados correctamente.',
            'role' => $encontrado,
        ]);
    }
}
