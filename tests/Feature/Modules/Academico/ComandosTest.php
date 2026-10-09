<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use Database\Seeders\FacultadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class ComandosTest extends TestCase
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

    public function test_the_detect_command_lists_the_periods_with_their_state(): void
    {
        $this->travelTo('2026-10-12 15:00:00');

        $this->comando('periodos:detectar')
            ->expectsTable(
                ['Período', 'Tipo', 'Inicio', 'Fin', 'Estado'],
                [
                    ['2/2026', 'Semestre 2', '2026-08-10', '2026-12-26', 'Vigente'],
                    ['1/2026', 'Semestre 1', '2026-02-23', '2026-07-04', 'Cerrado'],
                    ['0/2026', 'Anual', '—', '—', 'Sin fechas'],
                ],
            )
            ->expectsOutputToContain('3 creados, 0 actualizados.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('periodos', 3);
    }

    public function test_the_import_command_imports_the_four_faculties(): void
    {
        $this->comando('oferta:importar')
            ->expectsOutputToContain('FCyT: 2 carreras, 3 asignaturas, 5 grupos (1 sin docente), 3 docentes, 7 aulas, 1 sesiones descartadas.')
            ->expectsOutputToContain('FCE: 2 carreras, 2 asignaturas, 5 grupos (3 sin docente)')
            ->expectsOutputToContain('FHCE: 1 carreras, 1 asignaturas, 1 grupos (1 sin docente)')
            ->expectsOutputToContain('FACH: 2 carreras, 2 asignaturas, 2 grupos (0 sin docente)')
            ->assertExitCode(0);

        $this->assertDatabaseCount('grupos', 13);
        $this->assertSame(4, DB::table('importaciones_oferta')->where('estado', 'IMPORTADA')->count());
    }

    public function test_the_import_command_accepts_a_single_faculty(): void
    {
        $this->comando('oferta:importar', ['facultad' => 'fach'])
            ->expectsOutputToContain('FACH: 2 carreras')
            ->assertExitCode(0);

        $this->assertDatabaseCount('importaciones_oferta', 1);
        $this->assertDatabaseCount('grupos', 2);
    }

    public function test_the_import_command_exits_with_one_when_a_faculty_fails(): void
    {
        $this->comando('oferta:importar', ['facultad' => 'fcjp'])
            ->expectsOutputToContain('La facultad «fcjp» no está en el catálogo.')
            ->assertExitCode(1);

        config(['umss.ruta_genda' => base_path('tests/Fixtures/no-existe')]);

        $this->comando('oferta:importar')
            ->expectsOutputToContain('FCyT: No se encontró el archivo fcyt.json.')
            ->assertExitCode(1);

        $this->assertSame(4, DB::table('importaciones_oferta')->where('estado', 'FALLO')->count());
    }

    /**
     * @param  array<string, string>  $argumentos
     */
    private function comando(string $nombre, array $argumentos = []): PendingCommand
    {
        $comando = $this->artisan($nombre, $argumentos);

        $this->assertInstanceOf(PendingCommand::class, $comando);

        return $comando;
    }
}
