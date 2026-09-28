<?php

namespace Database\Seeders;

use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sin roles ni permisos cargados, HU-02 no tiene con que trabajar.
        $this->call(RolePermissionSeeder::class);

        // Sin rol asignado la pantalla de usuarios muestra la cuenta sin
        // atribuciones y HU-02 no tiene de donde leerlas.
        $administrador = Role::where('name', 'Administrador')->first();

        User::create([
            'nombre' => 'Administrador',
            'correo' => 'admin@test.com',
            'role_id' => $administrador?->getKey(),
            'password' => Hash::make('password'),
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => null,
        ]);
    }
}
