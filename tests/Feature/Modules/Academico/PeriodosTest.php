<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Domain\Enums\EstadoImportacion;
use App\Modules\Academico\Domain\Enums\RegimenCarrera;
use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\ImportacionOferta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class PeriodosTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use OfertaDePrueba;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_faculties_come_in_catalogue_order_with_their_buildings_and_rooms(): void
    {
        $fce = $this->facultad('fce');
        $fcyt = $this->facultad('fcyt');
        $edificio = $this->edificio($fcyt);
        $this->aula('691B', $edificio);
        $this->aula('691C', $edificio);
        $this->aula('AULVIR');

        $this->actingAs($this->usuarioConPermisos([]))
            ->getJson('/api/facultades')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', [
                'id' => $fcyt->id,
                'clave' => 'fcyt',
                'sigla' => 'FCyT',
                'nombre' => 'Ciencias y Tecnología',
                'color' => '#B90813',
                'edificios' => 1,
                'aulas' => 3,
            ])
            ->assertJsonPath('data.1.id', $fce->id)
            ->assertJsonPath('data.1.edificios', 0);
    }

    public function test_faculties_require_a_session(): void
    {
        $this->getJson('/api/facultades')->assertUnauthorized();
    }

    public function test_periods_list_current_ones_first_then_most_recent(): void
    {
        $this->periodo('1/2031', 30, 120, TipoPeriodo::Semestre1);
        $vigente = $this->periodo('2/2030', -10, 10);
        $this->periodo('0/2030', null, null, TipoPeriodo::Anual);
        $this->periodo('1/2030', -200, -20, TipoPeriodo::Semestre1);

        $fcyt = $this->facultad();
        $carrera = $this->carrera($fcyt, 'Ingeniería de Sistemas');
        $anual = $this->carrera($fcyt, 'Arquitectura', RegimenCarrera::Anual);
        $asignatura = $this->asignatura();
        $this->enPlan($carrera, $asignatura, 'SEMESTRE 1');
        $this->enPlan($anual, $asignatura, 'PRIMER AÑO');
        $this->grupo(null, $asignatura, '1', $vigente);
        $this->grupo(null, $asignatura, '2', $vigente);

        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->getJson('/api/periodos')
            ->assertOk()
            ->assertJsonPath('data.*.codigo', ['2/2030', '1/2031', '1/2030', '0/2030'])
            ->assertJsonPath('data.*.estado', ['Vigente', 'Próximo', 'Cerrado', 'Sin fechas'])
            ->assertJsonPath('data.0.id', $vigente->id)
            ->assertJsonPath('data.0.tipo', 'Semestre 2')
            ->assertJsonPath('data.0.fecha_inicio', now()->subDays(10)->toDateString())
            ->assertJsonPath('data.0.fecha_fin', now()->addDays(10)->toDateString())
            ->assertJsonPath('data.0.nota', '1 carrera · 2 grupos')
            ->assertJsonPath('data.3.fecha_inicio', null)
            ->assertJsonPath('data.3.nota', 'Sin oferta importada')
            ->assertJsonPath('meta', ['total' => 4, 'pagina' => 1, 'por_pagina' => 4]);
    }

    public function test_periods_are_paginated_on_the_server(): void
    {
        foreach ([2025, 2026, 2027, 2028, 2029] as $anio) {
            $this->periodo('2/'.$anio, null, null);
        }

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $sesion->getJson('/api/periodos')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.total', 5);

        $sesion->getJson('/api/periodos?pagina=2&por_pagina=2')
            ->assertOk()
            ->assertJsonPath('data.*.codigo', ['2/2027', '2/2026'])
            ->assertJsonPath('meta', ['total' => 5, 'pagina' => 2, 'por_pagina' => 2]);
    }

    public function test_periods_reject_an_invalid_page(): void
    {
        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->getJson('/api/periodos?pagina=0&por_pagina=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pagina', 'por_pagina'])
            ->assertJsonPath('errors.por_pagina.0', 'El campo por página no puede pasar de 100.');
    }

    public function test_periods_require_session_and_permission(): void
    {
        $this->getJson('/api/periodos')->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos(['aulas_docentes']))
            ->getJson('/api/periodos')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'periodo_oferta');
    }

    public function test_current_periods_are_available_to_any_session_with_the_main_one(): void
    {
        $semestre = $this->periodo('2/2030', -10, 10);
        $semestre->forceFill(['ventanas' => ['primeros_parciales' => ['2030-10-12', '2030-10-31']]])->save();
        $anual = $this->periodo('0/2030', -200, 100, TipoPeriodo::Anual);
        $this->periodo('1/2030', -200, -20, TipoPeriodo::Semestre1);

        $this->grupo(null, null, null, $semestre);
        $this->grupo(null, null, null, $semestre);
        $this->grupo(null, null, null, $anual);

        $this->actingAs($this->usuarioConPermisos([]))
            ->getJson('/api/periodos/vigentes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $semestre->id)
            ->assertJsonPath('data.0.codigo', '2/2030')
            ->assertJsonPath('data.0.tipo', 'Semestre 2')
            ->assertJsonPath('data.0.fecha_fin', now()->addDays(10)->toDateString())
            ->assertJsonPath('data.0.ventanas.primeros_parciales', ['2030-10-12', '2030-10-31'])
            ->assertJsonPath('data.1.codigo', '0/2030')
            ->assertJsonPath('principal', '2/2030');
    }

    public function test_current_periods_are_empty_when_today_is_outside_every_period(): void
    {
        $this->periodo('1/2030', -200, -20, TipoPeriodo::Semestre1);

        $this->actingAs($this->usuarioConPermisos([]))
            ->getJson('/api/periodos/vigentes')
            ->assertOk()
            ->assertExactJson(['data' => [], 'principal' => null]);
    }

    public function test_current_periods_require_a_session(): void
    {
        $this->getJson('/api/periodos/vigentes')->assertUnauthorized();
    }

    public function test_adjusting_a_period_saves_the_dates_and_who_set_them(): void
    {
        $periodo = $this->periodo('0/2030', null, null, TipoPeriodo::Anual);
        $administrador = $this->usuarioConPermisos(['periodo_oferta']);
        $inicio = now()->subDays(30)->toDateString();
        $fin = now()->addDays(200)->toDateString();

        $this->actingAs($administrador)
            ->putJson("/api/periodos/{$periodo->id}", ['fecha_inicio' => $inicio, 'fecha_fin' => $fin])
            ->assertOk()
            ->assertJsonPath('data.id', $periodo->id)
            ->assertJsonPath('data.codigo', '0/2030')
            ->assertJsonPath('data.tipo', 'Anual')
            ->assertJsonPath('data.fecha_inicio', $inicio)
            ->assertJsonPath('data.fecha_fin', $fin)
            ->assertJsonPath('data.estado', 'Vigente');

        $periodo->refresh();

        $this->assertSame($inicio, $periodo->fecha_inicio?->toDateString());
        $this->assertSame($fin, $periodo->fecha_fin?->toDateString());
        $this->assertSame($administrador->id, $periodo->ajustado_por);
    }

    public function test_adjusting_a_period_is_recorded_in_the_log(): void
    {
        $periodo = $this->periodo('0/2030', null, null, TipoPeriodo::Anual);
        $administrador = $this->usuarioConPermisos(['periodo_oferta']);

        $this->actingAs($administrador)
            ->putJson("/api/periodos/{$periodo->id}", ['fecha_inicio' => '2030-02-02', 'fecha_fin' => '2030-12-19'])
            ->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->id,
            'operacion' => 'periodo.ajustar',
            'tabla_afectada' => 'periodos',
            'registro_id' => $periodo->id,
        ]);
    }

    public function test_adjusting_a_period_validates_the_dates(): void
    {
        $periodo = $this->periodo('0/2030', null, null, TipoPeriodo::Anual);
        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $sesion->putJson("/api/periodos/{$periodo->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_inicio', 'fecha_fin'])
            ->assertJsonPath('errors.fecha_inicio.0', 'El campo fecha de inicio es obligatorio.');

        $sesion->putJson("/api/periodos/{$periodo->id}", ['fecha_inicio' => '2030-12-19', 'fecha_fin' => '2030-12-19'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fecha_fin.0', 'La fecha de fin debe ser posterior a la fecha de inicio.');

        $sesion->putJson("/api/periodos/{$periodo->id}", ['fecha_inicio' => '02/02/2030', 'fecha_fin' => '2030-12-19'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_inicio']);

        $this->assertNull($periodo->refresh()->fecha_inicio);
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'periodo.ajustar']);
    }

    public function test_adjusting_an_unknown_period_is_not_found(): void
    {
        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->putJson('/api/periodos/999', ['fecha_inicio' => '2030-02-02', 'fecha_fin' => '2030-12-19'])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Período no encontrado.']);
    }

    public function test_adjusting_a_period_requires_session_and_permission(): void
    {
        $periodo = $this->periodo('0/2030', null, null, TipoPeriodo::Anual);
        $cuerpo = ['fecha_inicio' => '2030-02-02', 'fecha_fin' => '2030-12-19'];

        $this->putJson("/api/periodos/{$periodo->id}", $cuerpo)->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos(['examenes']))
            ->putJson("/api/periodos/{$periodo->id}", $cuerpo)
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'periodo_oferta');

        $this->assertNull($periodo->refresh()->fecha_inicio);
    }

    public function test_summary_shows_the_main_period_its_exam_window_and_what_is_pending(): void
    {
        $periodo = $this->periodo('2/2030', -10, 60);
        $periodo->forceFill(['ventanas' => [
            'primeros_parciales' => [now()->subDays(40)->toDateString(), now()->subDays(30)->toDateString()],
            'segundos_parciales' => [now()->subDay()->toDateString(), now()->addDays(5)->toDateString()],
            'examenes_finales' => [now()->addDays(40)->toDateString(), now()->addDays(50)->toDateString()],
        ]])->save();
        $cerrado = $this->periodo('1/2030', -200, -20, TipoPeriodo::Semestre1);

        $fcyt = $this->facultad();
        $fce = $this->facultad('fce');
        $this->carrera($fcyt, 'Ingeniería de Sistemas');
        $this->carrera($fcyt, 'Ingeniería Civil');
        $this->aula('691B', $this->edificio($fcyt));

        $conCuenta = $this->docenteConCuenta();
        $this->docenteSinCuenta();
        $this->docenteSinCuenta();

        $conLista = $this->grupo($conCuenta, null, '1', $periodo, $fcyt);
        $this->grupo(null, null, '2', $periodo, $fcyt);
        $this->grupo(null, null, '3', $periodo, $fce);
        $this->grupo(null, null, '4', $cerrado, $fcyt);
        $this->estudiantesInscritos($conLista, 2);

        ImportacionOferta::create([
            'facultad_id' => $fcyt->id,
            'estado' => EstadoImportacion::Fallo,
            'error' => 'Archivo viejo',
            'iniciada_en' => now()->subDays(3),
        ]);
        ImportacionOferta::create([
            'facultad_id' => $fcyt->id,
            'estado' => EstadoImportacion::Importada,
            'fecha_fuente' => '2030-08-03',
            'iniciada_en' => now()->subDay(),
            'terminada_en' => now()->subDay(),
        ]);

        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->getJson('/api/periodos/resumen')
            ->assertOk()
            ->assertJsonPath('hoy', now()->toDateString())
            ->assertJsonPath('periodo', ['codigo' => '2/2030', 'estado' => 'Vigente'])
            ->assertJsonPath('ventana', [
                'nombre' => 'Segundos parciales',
                'desde' => now()->subDay()->toDateString(),
                'hasta' => now()->addDays(5)->toDateString(),
            ])
            ->assertJsonPath('pendientes.docentes_sin_cuenta', ['valor' => 2, 'de' => 3])
            ->assertJsonPath('pendientes.grupos_sin_lista', ['valor' => 2, 'de' => 3])
            ->assertJsonCount(2, 'facultades')
            ->assertJsonPath('facultades.0', [
                'sigla' => 'FCyT',
                'nombre' => 'Ciencias y Tecnología',
                'carreras' => 2,
                'grupos' => 2,
                'aulas' => 1,
                'importacion' => ['estado' => 'importada', 'fecha' => '2030-08-03', 'error' => null],
            ])
            ->assertJsonPath('facultades.1.sigla', 'FCE')
            ->assertJsonPath('facultades.1.grupos', 1)
            ->assertJsonPath('facultades.1.importacion', ['estado' => 'sin', 'fecha' => null, 'error' => null]);
    }

    public function test_summary_reports_a_failed_or_running_import_and_the_next_window(): void
    {
        $periodo = $this->periodo('2/2030', -10, 60);
        $periodo->forceFill(['ventanas' => [
            'examenes_finales' => [now()->addDays(40)->toDateString(), now()->addDays(50)->toDateString()],
            'segundos_parciales' => [now()->addDays(10)->toDateString(), now()->addDays(15)->toDateString()],
        ]])->save();

        $fach = $this->facultad('fach');
        $fce = $this->facultad('fce');

        ImportacionOferta::create([
            'facultad_id' => $fach->id,
            'estado' => EstadoImportacion::Fallo,
            'error' => 'No se encontró el archivo fach.json.',
            'iniciada_en' => now(),
            'terminada_en' => now(),
        ]);
        ImportacionOferta::create([
            'facultad_id' => $fce->id,
            'estado' => EstadoImportacion::Importando,
            'iniciada_en' => now(),
        ]);

        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->getJson('/api/periodos/resumen')
            ->assertOk()
            ->assertJsonPath('ventana.nombre', 'Segundos parciales')
            ->assertJsonPath('facultades.0.sigla', 'FCE')
            ->assertJsonPath('facultades.0.importacion.estado', 'importando')
            ->assertJsonPath('facultades.1.importacion', [
                'estado' => 'fallo',
                'fecha' => now()->toDateString(),
                'error' => 'No se encontró el archivo fach.json.',
            ]);
    }

    public function test_summary_without_a_current_period_has_no_period_and_counts_every_group(): void
    {
        $cerrado = $this->periodo('1/2030', -200, -20, TipoPeriodo::Semestre1);
        $this->grupo(null, null, '1', $cerrado);

        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->getJson('/api/periodos/resumen')
            ->assertOk()
            ->assertJsonPath('periodo', null)
            ->assertJsonPath('ventana', null)
            ->assertJsonPath('pendientes.docentes_sin_cuenta', ['valor' => 0, 'de' => 0])
            ->assertJsonPath('pendientes.grupos_sin_lista', ['valor' => 1, 'de' => 1]);
    }

    public function test_summary_requires_session_and_permission(): void
    {
        $this->getJson('/api/periodos/resumen')->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos(['mis_grupos']))
            ->getJson('/api/periodos/resumen')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'periodo_oferta');
    }
}
