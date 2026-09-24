<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Http\JsonResponse;

class RoleController
{
    public function getUsers(): JsonResponse
    {
        /** @phpstan-ignore-next-line */
        $users = User::with('role')->get();
        return response()->json($users);
    }

    public function getRoles(): JsonResponse
    {
        /** @phpstan-ignore-next-line */
        $roles = Role::with('permissions')->get();
        return response()->json($roles);
    }
}