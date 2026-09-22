<?php

namespace Database\Factories;

use App\Modules\Administracion\Infrastructure\Persistence\EloquentStudent;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    
protected $model = \App\Modules\Administracion\Domain\Models\EloquentStudent::class;

    public function definition()
    {
        return [
            'nombre'   => $this->faker->firstName,
            'apellido' => $this->faker->lastName,
            'ci'       => $this->faker->unique()->numerify('######'),
            'correo'   => $this->faker->unique()->safeEmail,
            'activo'   => true,
        ];
    }
}
