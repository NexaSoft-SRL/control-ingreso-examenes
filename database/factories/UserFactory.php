<?php

namespace Database\Factories;

use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),

            // Unico, en minusculas y sin espacios.
            'usuario' => 'usuario.'.fake()->unique()->numerify('######'),

            'correo' => fake()->unique()->safeEmail(),

            'password' => static::$password ??= Hash::make('password'),

            // Una cuenta de prueba ya definio su contrasena; la temporal se
            // prueba poniendo esta fecha en null.
            'password_changed_at' => now(),

            'is_active' => true,

            'failed_login_attempts' => 0,

            'locked_until' => null,

            'last_login_at' => null,

            // Desde HU-02 las rutas exigen el permiso del rol: una cuenta
            // sin rol no puede hacer nada. Quien necesite un rol acotado lo
            // pasa explicitamente en role_id.
            'role_id' => $this->rolAdministrador(),
        ];
    }

    private function rolAdministrador(): int
    {
        $rol = Role::where('name', 'Administrador')->first();

        if (! $rol instanceof Role) {
            (new RolePermissionSeeder)->run();

            $rol = Role::where('name', 'Administrador')->firstOrFail();
        }

        $id = $rol->getKey();

        if (! is_int($id)) {
            throw new \LogicException('El rol Administrador no tiene un identificador entero.');
        }

        return $id;
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
