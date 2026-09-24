<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;

class RoleController 
{
    public function getUsers()
    {
        $users = User::with('role')->get();
        return response()->json($users);
    }

    public function getRoles()
    {
        $roles = Role::with('permissions')->get();
        return response()->json($roles);
    }
}