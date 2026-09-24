<?php

namespace Database\Seeders;

use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'nombre' => 'Administrador',
            'correo' => 'admin@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => null,
        ]);
    }
}