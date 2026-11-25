<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Domain\Enums\RegimenCarrera;
use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Carrera;
use App\Modules\Academico\Domain\Models\Facultad;
use App\Modules\Academico\Domain\Models\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Los filtros de la Oferta van encadenados: período, facultad y carrera solo
 * ofrecen lo que tiene oferta en el período elegido.
 *
 * Escenario: la FCyT solo tiene carreras semestrales; la FACH, una anual y una
 * semestral; el 2/2026 y el anual 0/2026 están vigentes y el 1/2026 es un
 * semestre cerrado con oferta importada del historial (sin la FACH anual).
 */
final class FiltrosDeOfertaTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use OfertaDePrueba;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private Facultad $fcyt;

    private Facultad $fach;

    private Facultad $fce;

    private Carrera $sistemas;

    private Carrera $arquitectura;

    private Carrera $turismo;

    private Periodo $semestre2;

    private Periodo $anual;

    private Periodo $semestre1;

    private Periodo $sinOferta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->semestre2 = $this->periodo('2/2030', -10, 60);
        $this->anual = $this->periodo('0/2030', -100, 200, TipoPeriodo::Anual);
        $this->semestre1 = $this->periodo('1/2030', -250, -100, TipoPeriodo::Semestre1);
        // Un período detectado al que no se le importó oferta.
        $this->sinOferta = $this->periodo('4/2030', null, null, TipoPeriodo::Invierno);

        $this->fcyt = $this->facultad('fcyt');
        $this->fach = $this->facultad('fach');
        $this->fce = $this->facultad('fce');
        $this->sistemas = $this->carrera($this->fcyt, 'Ingeniería de Sistemas');
        $this->arquitectura = $this->carrera($this->fach, 'Arquitectura', RegimenCarrera::Anual);
        $this->turismo = $this->carrera($this->fach, 'Turismo');
        $this->carrera($this->fce, 'Economía');

        $algebra = $this->asignatura('Álgebra');
        $diseno = $this->asignatura('Diseño I');
        $hospedaje = $this->asignatura('Hospedaje');
        $this->enPlan($this->sistemas, $algebra, 'SEMESTRE 1');
        $this->enPlan($this->arquitectura, $diseno, 'PRIMER AÑO');
        $this->enPlan($this->turismo, $hospedaje, 'SEMESTRE 3');

        // Vigentes: FCyT en el 2/2030; FACH anual en el anual y su carrera
        // semestral en el 2/2030.
        $this->grupo(null, $algebra, '1', $this->semestre2, $this->fcyt);
        $this->grupo(null, $diseno, '1', $this->anual, $this->fach);
        $this->grupo(null, $hospedaje, '1', $this->semestre2, $this->fach);
        // Historial: el 1/2030 tiene FCyT y FACH (solo la semestral), no el anual.
        $this->grupo(null, $algebra, '1', $this->semestre1, $this->fcyt);
        $this->grupo(null, $hospedaje, '1', $this->semestre1, $this->fach);
    }

    private function administracion(): static
    {
        return $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));
    }

    public function test_periods_report_how_many_groups_they_have(): void
    {
        $filas = $this->administracion()->getJson('/api/periodos?por_pagina=10')->assertOk()->json('data');

        $this->assertIsArray($filas);
        $grupos = collect($filas)->pluck('grupos', 'codigo')->all();

        $this->assertEqualsCanonicalizing(['2/2030' => 2, '0/2030' => 1, '1/2030' => 2, '4/2030' => 0], $grupos);
        $this->assertSame(2, $grupos['2/2030'] ?? null);
        $this->assertSame(0, $grupos['4/2030'] ?? null);
    }

    public function test_the_current_periods_offer_every_faculty_that_has_groups_in_them(): void
    {
        $this->administracion()
            ->getJson('/api/oferta/facultades')
            ->assertOk()
            ->assertExactJson(['data' => ['fcyt', 'fach']]);
    }

    public function test_the_annual_period_only_offers_the_annual_faculties(): void
    {
        $this->administracion()
            ->getJson("/api/oferta/facultades?periodo={$this->anual->id}")
            ->assertOk()
            ->assertExactJson(['data' => ['fach']]);
    }

    public function test_a_closed_semester_offers_the_faculties_of_its_imported_offer(): void
    {
        $this->administracion()
            ->getJson("/api/oferta/facultades?periodo={$this->semestre1->id}")
            ->assertOk()
            ->assertExactJson(['data' => ['fcyt', 'fach']]);
    }

    public function test_a_period_without_offer_has_no_faculties(): void
    {
        $this->administracion()
            ->getJson("/api/oferta/facultades?periodo={$this->sinOferta->id}")
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_the_faculties_of_the_offer_validate_the_period_and_need_the_permission(): void
    {
        $this->getJson('/api/oferta/facultades')->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos(['padron_estudiantes']))
            ->getJson('/api/oferta/facultades')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'periodo_oferta');

        $this->administracion()
            ->getJson('/api/oferta/facultades?periodo=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['periodo']);
    }

    public function test_careers_without_asking_for_offer_are_all_of_the_faculty(): void
    {
        // El Padrón las pide así: todas, tengan o no oferta.
        $this->administracion()
            ->getJson('/api/oferta/carreras?facultad=fach')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Arquitectura', 'Turismo']);
    }

    public function test_careers_of_the_current_periods_match_each_period_by_regime(): void
    {
        $this->administracion()
            ->getJson('/api/oferta/carreras?facultad=fach&con_oferta=1')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Arquitectura', 'Turismo']);

        // La FCE no tiene oferta en ningún vigente.
        $this->administracion()
            ->getJson('/api/oferta/carreras?facultad=fce&con_oferta=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_annual_period_only_offers_the_annual_careers(): void
    {
        $this->administracion()
            ->getJson("/api/oferta/carreras?facultad=fach&periodo={$this->anual->id}")
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Arquitectura'])
            ->assertJsonPath('data.0.regimen', 'Anual');
    }

    public function test_a_semester_only_offers_the_semester_careers(): void
    {
        $this->administracion()
            ->getJson("/api/oferta/carreras?facultad=fach&periodo={$this->semestre2->id}")
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Turismo']);

        $this->administracion()
            ->getJson("/api/oferta/carreras?facultad=fcyt&periodo={$this->semestre1->id}")
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Ingeniería de Sistemas']);
    }

    public function test_the_levels_follow_the_period_and_the_career(): void
    {
        $niveles = fn (string $consulta): mixed => $this->administracion()
            ->getJson("/api/oferta/asignaturas?{$consulta}")
            ->assertOk()
            ->json('meta.niveles');

        // El anual solo tiene el nivel de la carrera anual; el semestre 2, el de la semestral.
        $this->assertSame(['Primer año'], $niveles("facultad=fach&periodo={$this->anual->id}"));
        $this->assertSame(['Semestre 3'], $niveles("facultad=fach&periodo={$this->semestre2->id}"));
        $this->assertSame(['Semestre 1'], $niveles("facultad=fcyt&carrera={$this->sistemas->id}"));
    }
}
