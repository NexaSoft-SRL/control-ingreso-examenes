<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentStudent;

class EloquentStudentFactory extends Factory
{
    protected $model = EloquentStudent::class;

    public function definition(): array
    {
        return [
            'nombre'   => $this->faker->firstName,
            'apellido' => $this->faker->lastName,
            'ci'       => $this->faker->unique()->numerify('#######'),
            'correo'   => $this->faker->unique()->safeEmail,
            'activo'   => true,
        ];
    }
}
