<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Administracion\Domain\Models\Ambiente;
use Faker\Factory as Faker;
use Faker\Generator;

class AmbienteFactory
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
    public function create(array $attributes = []): Ambiente
    {
        /** @var array<string, mixed> $data */
        $data = array_merge([
            'nombre' => 'Aula '.$this->faker->unique()->numberBetween(100, 999),
            'ubicacion' => 'Edificio '.$this->faker->randomLetter(),
            'capacidad' => $this->faker->numberBetween(20, 100),
            'estado' => 'DISPONIBLE',
        ], $attributes);

        return Ambiente::query()->create($data);
    }
}
