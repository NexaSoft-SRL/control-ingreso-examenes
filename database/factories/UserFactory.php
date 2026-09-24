<?php

namespace Database\Factories;

use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

            'correo' => fake()->unique()->safeEmail(),

            'password' => static::$password ??= Hash::make('password'),

            'is_active' => true,

            'failed_login_attempts' => 0,

            'locked_until' => null,

            'last_login_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}