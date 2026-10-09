<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Examenes\Domain\Models\PlantillaNorma;
use Illuminate\Database\Seeder;

/**
 * Las normas predefinidas del sistema: plantillas sin cuenta, que todo
 * docente ve al registrar un examen. Se puede correr mas de una vez: no
 * duplica (se reconocen por su texto).
 */
class NormasPredefinidasSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const NORMAS = [
        'Documento de identidad a la vista',
        'Sin celular',
        'Sin apuntes ni libros',
        'Sin calculadora programable',
        'Solo bolígrafo azul o negro',
        'No se ingresa pasados 15 minutos',
    ];

    public function run(): void
    {
        foreach (self::NORMAS as $texto) {
            PlantillaNorma::firstOrCreate([
                'usuario_id' => null,
                'texto' => $texto,
            ]);
        }
    }
}
