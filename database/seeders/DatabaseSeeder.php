<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Lo minimo para que el sistema arranque vacio: los roles de inicio con sus
 * permisos, el catalogo de facultades, las normas predefinidas y una cuenta
 * de administrador. Los datos de demostracion son de `DemoSeeder`
 * (`composer datos:demo`). Se puede correr mas de una vez.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            FacultadSeeder::class,
            NormasPredefinidasSeeder::class,
        ]);

        $administrador = Role::where('name', 'Administrador')->firstOrFail();

        User::firstOrCreate(
            ['correo' => 'admin@test.com'],
            [
                'nombre' => 'Administrador',
                'usuario' => 'admin',
                'role_id' => $administrador->id,
                'password' => Hash::make('password'),
                'password_changed_at' => now(),
                'is_active' => true,
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'last_login_at' => null,
            ],
        );
    }
}
