<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Administracion\Domain\Models\Student;
use Faker\Factory as Faker;
use Faker\Generator;

/**
 * Factory manual para Student.
 *
 * Student no usa HasFactory (para respetar Deptrac), por lo que
 * esta factory construye la entidad sin pasar por el factory
 * automatico de Eloquent.
 */
class StudentFactory
{
    protected Generator $faker;

    public function __construct()
    {
        $this->faker = Faker::create();
    }

    public static function new(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes = []): Student
    {
        /** @var array<string, mixed> $data */
        $data = array_merge([
            'nombre' => $this->faker->firstName(),
            'apellido' => $this->faker->lastName(),
            'ci' => $this->faker->unique()->numerify('########'),
            'correo' => $this->faker->unique()->safeEmail(),
            'activo' => true,
        ], $attributes);

        return Student::query()->create($data);
    }
}
