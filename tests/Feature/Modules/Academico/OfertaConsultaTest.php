<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Domain\Enums\RegimenCarrera;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class OfertaConsultaTest extends TestCase
{
    use DatosAcademicos;
    use OfertaDePrueba;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_careers_are_listed_by_name_and_filtered_by_faculty(): void
    {
        $fcyt = $this->facultad();
        $fach = $this->facultad('fach');
        $sistemas = $this->carrera($fcyt, 'Licenciatura en Ingeniería de Sistemas');
        $this->carrera($fcyt, 'Licenciatura en Física');
        $this->carrera($fach, 'Arquitectura', RegimenCarrera::Anual);

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $sesion->getJson('/api/oferta/carreras?facultad=fcyt')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Licenciatura en Física', 'Licenciatura en Ingeniería de Sistemas'])
            ->assertJsonPath('data.1', [
                'id' => $sistemas->id,
                'codigo' => $sistemas->codigo,
                'nombre' => 'Licenciatura en Ingeniería de Sistemas',
                'regimen' => 'Semestral',
            ]);

        $sesion->getJson('/api/oferta/carreras?facultad=FACH')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.regimen', 'Anual');

        $sesion->getJson('/api/oferta/carreras')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_careers_reject_an_unknown_faculty(): void
    {
        $this->facultad();

        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->getJson('/api/oferta/carreras?facultad=fcjp')
            ->assertUnprocessable()
            ->assertJsonPath('errors.facultad.0', 'La facultad indicada no existe.');
    }

    public function test_careers_accept_either_the_offer_or_the_roster_permission(): void
    {
        $this->getJson('/api/oferta/carreras')->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos(['padron_estudiantes']))
            ->getJson('/api/oferta/carreras')
            ->assertOk();

        $this->actingAs($this->usuarioConRol('Docente'))
            ->getJson('/api/oferta/carreras')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'periodo_oferta|padron_estudiantes')
            ->assertJsonPath('rol', 'Docente');
    }
}
