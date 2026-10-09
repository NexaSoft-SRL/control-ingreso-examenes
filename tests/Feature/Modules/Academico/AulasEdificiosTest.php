<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class AulasEdificiosTest extends TestCase
{
    use DatosAcademicos;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_buildings_come_whole_with_polygon_rooms_floors_and_the_bounding_box(): void
    {
        $fcyt = $this->facultad();
        $laboratorios = $this->edificio($fcyt, 'Edificio de Laboratorios');
        $academico = $this->edificio($fcyt, 'Edificio Académico');
        $this->aula('680J', $laboratorios);
        $this->aula('680B', $laboratorios);
        $this->aula('A741', $academico, '2° Piso');
        $this->aula('A740', $academico, '1° Piso');
        $this->aula('A75', $academico, '1° Piso');
        $this->aula('AULVIR');

        $respuesta = $this->actingAs($this->usuarioConPermisos([]))
            ->getJson('/api/edificios')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $academico->id)
            ->assertJsonPath('data.0.clave', $academico->clave)
            ->assertJsonPath('data.0.facultad', 'FCyT')
            ->assertJsonPath('data.0.nombre', 'Edificio Académico')
            ->assertJsonPath('data.0.aulas', ['A75', 'A740', 'A741'])
            ->assertJsonPath('data.0.pisos', [
                ['nombre' => '1° Piso', 'aulas' => ['A75', 'A740']],
                ['nombre' => '2° Piso', 'aulas' => ['A741']],
            ])
            ->assertJsonPath('data.1.aulas', ['680B', '680J'])
            ->assertJsonPath('data.1.pisos', [])
            ->assertJsonPath('data.1.poligono.0', [-66.14435, -17.39445])
            ->assertJsonCount(4, 'data.1.poligono')
            ->assertJsonPath('data.1.centro', [-66.1443167, -17.3944833])
            ->assertJsonPath('meta.caja', [
                'lon' => [-66.14435, -66.14425],
                'lat' => [-17.39455, -17.39445],
            ]);

        $cache = (string) $respuesta->headers->get('Cache-Control');

        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('max-age=3600', $cache);
        $this->assertStringNotContainsString('no-cache', $cache);
    }

    public function test_buildings_are_filtered_by_faculty(): void
    {
        $this->edificio($this->facultad(), 'Edificio FCyT');
        $this->edificio($this->facultad('fce'), 'Edificio FCE');
        $this->facultad('fach');

        $sesion = $this->actingAs($this->usuarioConPermisos([]));

        $sesion->getJson('/api/edificios?facultad=fce')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Edificio FCE'])
            ->assertJsonPath('data.0.facultad', 'FCE');

        $sesion->getJson('/api/edificios?facultad=fach')
            ->assertOk()
            ->assertExactJson(['data' => [], 'meta' => ['caja' => null]]);
    }

    public function test_buildings_reject_an_unknown_faculty_and_require_a_session(): void
    {
        $this->getJson('/api/edificios')->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos([]))
            ->getJson('/api/edificios?facultad=xyz')
            ->assertUnprocessable()
            ->assertJsonPath('errors.facultad.0', 'La facultad indicada no existe.');
    }

    public function test_rooms_come_whole_in_natural_order_with_building_and_floor(): void
    {
        $fcyt = $this->facultad();
        $edificio = $this->edificio($fcyt, 'Edificio Académico 2');
        $aula = $this->aula('624', $edificio, '1° Piso');
        $this->aula('62', $edificio);
        $sinUbicar = $this->aula('AULVIR');

        $this->actingAs($this->usuarioConPermisos([]))
            ->getJson('/api/aulas')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['62', '624', 'AULVIR'])
            ->assertJsonPath('data.1', [
                'id' => $aula->id,
                'nombre' => '624',
                'edificio_id' => $edificio->id,
                'edificio' => 'Edificio Académico 2',
                'piso' => '1° Piso',
                'facultad' => 'FCyT',
            ])
            ->assertJsonPath('data.2', [
                'id' => $sinUbicar->id,
                'nombre' => 'AULVIR',
                'edificio_id' => null,
                'edificio' => null,
                'piso' => null,
                'facultad' => 'FCyT',
            ]);
    }

    public function test_rooms_are_filtered_by_faculty_and_by_being_located(): void
    {
        $this->aula('691B', $this->edificio($this->facultad()));
        $this->aula('AULVIR');
        $this->aula('E12', $this->edificio($this->facultad('fce')));

        $sesion = $this->actingAs($this->usuarioConPermisos([]));

        $sesion->getJson('/api/aulas?ubicadas=1')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['691B', 'E12']);

        $sesion->getJson('/api/aulas?facultad=fcyt')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['691B', 'AULVIR']);

        $sesion->getJson('/api/aulas?facultad=fcyt&ubicadas=1')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['691B']);

        $sesion->getJson('/api/aulas?facultad=&ubicadas=0')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_rooms_validate_filters_and_require_a_session(): void
    {
        $this->getJson('/api/aulas')->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos([]))
            ->getJson('/api/aulas?ubicadas=quizas&facultad=xyz')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ubicadas', 'facultad'])
            ->assertJsonPath('errors.ubicadas.0', 'El campo ubicadas debe ser verdadero o falso.');
    }
}
