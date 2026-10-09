<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Application\Actions\ImportarUbicaciones;
use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;
use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Edificio;
use Database\Seeders\FacultadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ImportarUbicacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['umss.ruta_genda' => base_path('tests/Fixtures/genda')]);

        $this->seed(FacultadSeeder::class);
    }

    public function test_it_stores_buildings_with_polygon_centre_and_rooms(): void
    {
        $resultado = $this->app->make(ImportarUbicaciones::class)->execute('fcyt');

        $this->assertSame(['edificios' => 2, 'aulas' => 6], $resultado);

        $edificio = Edificio::where('clave', 'fcyt_blk_2')->firstOrFail();

        $this->assertSame('Edificio Academico 2', $edificio->nombre);
        $this->assertSame(DB::table('facultades')->where('clave', 'fcyt')->value('id'), $edificio->facultad_id);
        $this->assertCount(5, $edificio->poligono);
        $this->assertSame([-66.145, -17.393], $edificio->poligono[0]);

        // Promedio de los cuatro vertices: el quinto repite al primero.
        $this->assertEqualsWithDelta(-66.1448, $edificio->centro_lon, 0.0000001);
        $this->assertEqualsWithDelta(-17.3932, $edificio->centro_lat, 0.0000001);

        $this->assertDatabaseHas('aulas', ['nombre' => '691A', 'edificio_id' => $edificio->id, 'piso' => '1° Piso']);
        $this->assertDatabaseHas('aulas', ['nombre' => '692A', 'edificio_id' => $edificio->id, 'piso' => '2° Piso']);

        // Las aulas sueltas del edificio no tienen piso conocido.
        $this->assertDatabaseHas('aulas', [
            'nombre' => '617',
            'edificio_id' => Edificio::where('clave', 'fcyt_blk_1')->value('id'),
            'piso' => null,
        ]);
    }

    public function test_running_it_again_does_not_duplicate_and_locates_known_rooms(): void
    {
        // El aula ya existia sin ubicar, traida por la oferta.
        Aula::create([
            'nombre' => '624',
            'edificio_id' => null,
            'facultad_id' => DB::table('facultades')->where('clave', 'fce')->value('id'),
        ]);

        $importar = $this->app->make(ImportarUbicaciones::class);

        $importar->execute('fcyt');
        $importar->execute('fcyt');

        $this->assertDatabaseCount('edificios', 2);
        $this->assertDatabaseCount('aulas', 6);
        $this->assertDatabaseHas('aulas', [
            'nombre' => '624',
            'edificio_id' => Edificio::where('clave', 'fcyt_blk_1')->value('id'),
            'facultad_id' => DB::table('facultades')->where('clave', 'fcyt')->value('id'),
        ]);
    }

    public function test_only_the_requested_faculty_is_imported_and_unknown_ones_are_rejected(): void
    {
        $importar = $this->app->make(ImportarUbicaciones::class);

        // FHCE no tiene edificios en la fuente de prueba.
        $this->assertSame(['edificios' => 0, 'aulas' => 0], $importar->execute('fhce'));
        $this->assertSame(['edificios' => 1, 'aulas' => 1], $importar->execute('fce'));

        $this->assertDatabaseCount('edificios', 1);
        $this->assertDatabaseMissing('aulas', ['nombre' => 'J-1']);

        $this->expectException(FuenteNoDisponibleException::class);

        // Esta en `locations.json`, pero no es una facultad del sistema.
        $importar->execute('fcjp');
    }
}
