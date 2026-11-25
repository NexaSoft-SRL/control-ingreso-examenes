<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Application\Actions\ImportarOferta;
use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;
use Database\Seeders\FacultadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * La oferta guardada de un periodo cerrado (`historial/1-2026/`) se importa
 * con la misma logica que la vigente, sin tocarla: ni sus grupos, ni el
 * estado de su importacion, ni lo anual.
 */
final class ImportarOfertaHistorialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'umss.ruta_genda' => base_path('tests/Fixtures/genda'),
            'umss.ruta_fuentes' => base_path('tests/Fixtures/genda/fuentes.json'),
        ]);

        $this->seed(FacultadSeeder::class);
    }

    private function importar(): ImportarOferta
    {
        return $this->app->make(ImportarOferta::class);
    }

    private function gruposDe(string $codigo): int
    {
        return DB::table('grupos')
            ->join('periodos', 'periodos.id', '=', 'grupos.periodo_id')
            ->where('periodos.codigo', $codigo)
            ->count();
    }

    public function test_it_imports_a_closed_period_without_touching_the_current_one(): void
    {
        $vigente = $this->importar()->execute('fcyt');
        $this->assertTrue($vigente->importada);
        $grupos2 = $this->gruposDe('2/2026');
        $anual = $this->gruposDe('0/2026');
        $importaciones = DB::table('importaciones_oferta')->count();
        $huella = DB::table('importaciones_oferta')->orderByDesc('id')->value('huella');

        $historial = $this->importar()->historial('fcyt', '1/2026');

        $this->assertTrue($historial->importada);
        $this->assertSame(2, $historial->grupos);
        $this->assertSame(2, $this->gruposDe('1/2026'));
        // Lo vigente y lo anual quedan igual.
        $this->assertSame($grupos2, $this->gruposDe('2/2026'));
        $this->assertSame($anual, $this->gruposDe('0/2026'));
        // La importación de la facultad sigue siendo la vigente: sin fila nueva y con su huella.
        $this->assertSame($importaciones, DB::table('importaciones_oferta')->count());
        $this->assertSame($huella, DB::table('importaciones_oferta')->orderByDesc('id')->value('huella'));
        $this->assertSame('2/2026', DB::table('importaciones_oferta')->orderByDesc('id')->value('periodo_codigo'));
    }

    public function test_the_annual_careers_of_the_history_do_not_enter(): void
    {
        $this->importar()->historial('fcyt', '1/2026');

        $this->assertSame(0, $this->gruposDe('0/2026'));
        $this->assertDatabaseMissing('asignaturas', ['codigo' => '2020001']);
        $this->assertDatabaseHas('asignaturas', ['codigo' => '2008099']);
    }

    public function test_importing_it_again_does_not_duplicate(): void
    {
        $this->importar()->historial('fcyt', '1/2026');
        $grupos = DB::table('grupos')->count();
        $docentes = DB::table('docentes')->count();
        $asignaturas = DB::table('asignaturas')->count();
        $horarios = DB::table('horarios')->count();

        $segunda = $this->importar()->historial('fcyt', '1/2026');

        $this->assertTrue($segunda->importada);
        $this->assertSame($grupos, DB::table('grupos')->count());
        $this->assertSame($docentes, DB::table('docentes')->count());
        $this->assertSame($asignaturas, DB::table('asignaturas')->count());
        $this->assertSame($horarios, DB::table('horarios')->count());
    }

    public function test_the_history_does_not_delete_the_groups_of_the_current_period(): void
    {
        $this->importar()->execute('fcyt');
        $antes = $this->gruposDe('2/2026');

        $resumen = $this->importar()->historial('fcyt', '1/2026');

        $this->assertSame(0, $resumen->gruposEliminados);
        $this->assertSame($antes, $this->gruposDe('2/2026'));
    }

    public function test_the_run_is_recorded_in_the_log_and_not_as_an_import(): void
    {
        $this->importar()->historial('fcyt', '1/2026');

        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'oferta.importar_historial']);
        $this->assertSame(0, DB::table('importaciones_oferta')->count());
    }

    public function test_a_period_without_saved_offer_says_so(): void
    {
        $resumen = $this->importar()->historial('fcyt', '1/2031');

        $this->assertFalse($resumen->importada);
        $this->assertSame('No hay oferta guardada del período 1/2031 para fcyt.', $resumen->error);
        $this->assertSame(0, DB::table('grupos')->count());
    }

    public function test_an_invalid_period_and_an_unknown_faculty_are_refused(): void
    {
        $this->assertSame(
            'El período «uno» no es válido (ejemplo: 1/2026).',
            $this->importar()->historial('fcyt', 'uno')->error,
        );

        $this->expectException(FuenteNoDisponibleException::class);
        $this->importar()->historial('fcjp', '1/2026');
    }

    public function test_the_artisan_command_imports_a_faculty_and_fails_without_saved_offer(): void
    {
        $this->comando('fcyt')
            ->expectsOutputToContain('FCyT 1/2026: 2 asignaturas, 2 grupos')
            ->assertExitCode(0);

        $this->comando('fce')->assertExitCode(1);
    }

    private function comando(string $facultad): PendingCommand
    {
        $comando = $this->artisan('oferta:importar-historial', ['periodo' => '1/2026', 'facultad' => $facultad]);

        $this->assertInstanceOf(PendingCommand::class, $comando);

        return $comando;
    }
}
