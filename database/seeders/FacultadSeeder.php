<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Academico\Domain\Models\Facultad;
use Illuminate\Database\Seeder;

/**
 * Catalogo fijo: las cuatro facultades cuya oferta se importa. La clave es
 * el nombre de su archivo en `database/umss/genda`. Se puede correr mas de
 * una vez.
 */
class FacultadSeeder extends Seeder
{
    /**
     * clave => [sigla, nombre, codigo de la universidad, color].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public const FACULTADES = [
        'fcyt' => ['FCyT', 'Ciencias y Tecnología', '20', '#B90813'],
        'fce' => ['FCE', 'Ciencias Económicas', '13', '#107C41'],
        'fhce' => ['FHCE', 'Humanidades y Ciencias de la Educación', '18', '#ea580c'],
        'fach' => ['FACH', 'Arquitectura y Ciencias del Hábitat', '17', '#154075'],
    ];

    public function run(): void
    {
        $orden = 0;

        foreach (self::FACULTADES as $clave => [$sigla, $nombre, $codigo, $color]) {
            Facultad::updateOrCreate(
                ['clave' => $clave],
                [
                    'sigla' => $sigla,
                    'nombre' => $nombre,
                    'codigo_umss' => $codigo,
                    'color' => $color,
                    'orden' => ++$orden,
                ],
            );
        }
    }
}
