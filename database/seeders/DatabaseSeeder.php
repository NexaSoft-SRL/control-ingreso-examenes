<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Lo minimo para que el sistema arranque vacio: los tres roles con sus
 * permisos, el catalogo de facultades y las normas predefinidas. Los datos de demostracion son de
 * `DemoSeeder` (`composer datos:demo`).
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
    }
}
