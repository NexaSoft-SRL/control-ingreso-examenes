<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Domain\Enums\EstadoImportacion;
use App\Modules\Academico\Domain\Models\ImportacionOferta;
use App\Modules\Academico\Domain\Models\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Rutas 22 y 24: llaman a las acciones del importador sobre una carpeta de
 * fuentes minima que arma la propia prueba.
 */
final class ImportacionPorHttpTest extends TestCase
{
    use DatosAcademicos;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private string $carpeta = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->carpeta = sys_get_temp_dir().'/genda-b2-'.bin2hex(random_bytes(6));
        mkdir($this->carpeta);

        config([
            'umss.ruta_genda' => $this->carpeta,
            'umss.ruta_fuentes' => $this->carpeta.'/fuentes.json',
            'umss.facultades' => ['fcyt'],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->carpeta.'/*') ?: [] as $archivo) {
            unlink($archivo);
        }

        if (is_dir($this->carpeta)) {
            rmdir($this->carpeta);
        }

        parent::tearDown();
    }

    public function test_detecting_periods_reports_created_and_updated(): void
    {
        $this->facultad();
        $this->escribirFuentes();
        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $sesion->postJson('/api/periodos/detectar')
            ->assertOk()
            ->assertExactJson(['creados' => 1, 'actualizados' => 0]);

        $periodo = Periodo::where('codigo', '2/2026')->firstOrFail();

        $this->assertSame('2026-08-10', $periodo->fecha_inicio?->toDateString());
        $this->assertSame('2026-12-26', $periodo->fecha_fin?->toDateString());

        // Detectar de nuevo no duplica.
        $sesion->postJson('/api/periodos/detectar')
            ->assertOk()
            ->assertExactJson(['creados' => 0, 'actualizados' => 1]);

        $this->assertSame(1, Periodo::count());
    }

    public function test_detecting_periods_requires_session_and_permission(): void
    {
        $this->postJson('/api/periodos/detectar')->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos(['aulas_docentes']))
            ->postJson('/api/periodos/detectar')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'periodo_oferta');

        $this->assertSame(0, Periodo::count());
    }

    public function test_importing_a_faculty_returns_the_summary(): void
    {
        $fcyt = $this->facultad();
        $this->escribirFuentes();
        $administrador = $this->usuarioConPermisos(['periodo_oferta']);

        $this->actingAs($administrador)
            ->postJson('/api/oferta/importaciones', ['facultad' => 'FCYT'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'importada')
            ->assertJsonPath('data.fecha', '2026-08-03')
            ->assertJsonPath('data.resumen.carreras', 1)
            ->assertJsonPath('data.resumen.asignaturas', 1)
            ->assertJsonPath('data.resumen.grupos', 2)
            ->assertJsonPath('data.resumen.grupos_sin_docente', 1)
            ->assertJsonPath('data.resumen.docentes', 1)
            ->assertJsonPath('data.resumen.aulas', 2)
            ->assertJsonPath('data.resumen.sesiones_descartadas', 0);

        $this->assertDatabaseHas('importaciones_oferta', [
            'facultad_id' => $fcyt->id,
            'estado' => EstadoImportacion::Importada->value,
            'ejecutada_por' => $administrador->id,
        ]);
        $this->assertDatabaseHas('docentes', ['nombre_normalizado' => 'TABORGA ACHA FIDEL']);
        $this->assertDatabaseCount('grupos', 2);

        // Lo importado se ve en las consultas de la oferta y del periodo.
        $this->assertDatabaseHas('asignaturas', ['codigo' => '2008019']);

        $this->getJson('/api/periodos/resumen')
            ->assertOk()
            ->assertJsonPath('facultades.0.importacion', ['estado' => 'importada', 'fecha' => '2026-08-03', 'error' => null]);
    }

    public function test_importing_is_recorded_in_the_log(): void
    {
        $this->facultad();
        $this->escribirFuentes();
        $administrador = $this->usuarioConPermisos(['periodo_oferta']);

        $this->actingAs($administrador)
            ->postJson('/api/oferta/importaciones', ['facultad' => 'fcyt'])
            ->assertCreated();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->id,
            'operacion' => 'oferta.importar',
        ]);
    }

    public function test_a_failed_import_answers_422_with_the_reason_and_leaves_nothing_half_done(): void
    {
        // La carpeta de fuentes esta vacia: no hay archivo de la facultad.
        $fcyt = $this->facultad();
        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $respuesta = $sesion->postJson('/api/oferta/importaciones', ['facultad' => 'fcyt'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No se pudo importar FCyT.')
            ->assertJsonPath('data.estado', 'fallo');

        $error = $respuesta->json('data.error');
        $this->assertIsString($error);
        $this->assertNotSame('', $error);

        $this->assertDatabaseHas('importaciones_oferta', [
            'facultad_id' => $fcyt->id,
            'estado' => EstadoImportacion::Fallo->value,
            'error' => $error,
        ]);
        $this->assertDatabaseCount('grupos', 0);

        $sesion->getJson('/api/periodos/resumen')
            ->assertOk()
            ->assertJsonPath('facultades.0.importacion.estado', 'fallo')
            ->assertJsonPath('facultades.0.importacion.error', $error);
    }

    public function test_an_import_in_progress_blocks_another_one_for_five_minutes(): void
    {
        $fcyt = $this->facultad();
        $this->escribirFuentes();
        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $enCurso = ImportacionOferta::create([
            'facultad_id' => $fcyt->id,
            'estado' => EstadoImportacion::Importando,
            'iniciada_en' => now()->subMinutes(2),
        ]);

        $sesion->postJson('/api/oferta/importaciones', ['facultad' => 'fcyt'])
            ->assertConflict()
            ->assertJsonPath('codigo', 'IMPORTACION_EN_CURSO');

        $this->assertDatabaseCount('importaciones_oferta', 1);

        // Pasados cinco minutos se la da por colgada y se puede reintentar.
        $enCurso->forceFill(['iniciada_en' => now()->subMinutes(6)])->save();

        $sesion->postJson('/api/oferta/importaciones', ['facultad' => 'fcyt'])->assertCreated();
    }

    public function test_importing_validates_the_faculty(): void
    {
        $this->facultad();
        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $sesion->postJson('/api/oferta/importaciones', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.facultad.0', 'El campo facultad es obligatorio.');

        $sesion->postJson('/api/oferta/importaciones', ['facultad' => 'fcjp'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.facultad.0', 'La facultad indicada no existe.');

        $this->assertDatabaseCount('importaciones_oferta', 0);
    }

    public function test_importing_requires_session_and_permission(): void
    {
        $this->facultad();
        $this->escribirFuentes();

        $this->postJson('/api/oferta/importaciones', ['facultad' => 'fcyt'])->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos(['padron_estudiantes']))
            ->postJson('/api/oferta/importaciones', ['facultad' => 'fcyt'])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'periodo_oferta');

        $this->assertDatabaseCount('importaciones_oferta', 0);
    }

    /**
     * Una facultad minima: una carrera, una asignatura y dos grupos (uno
     * sin docente), con su edificio y el calendario del periodo.
     */
    private function escribirFuentes(): void
    {
        $this->escribir('fcyt.json', [
            'INFO' => ['SEMESTER' => 'Semestre 2/2026', 'DATE' => '8/3/2026'],
            'LICENCIATURA EN INGENIERIA DE SISTEMAS' => [
                '_CAREER_CODE' => '411702',
                'SEMESTRE 1' => [
                    'ALGEBRA I' => [
                        '_CODE' => '2008019',
                        '5' => [
                            'PROFESSOR' => 'TABORGA ACHA FIDEL',
                            'AUX_INDEX' => -1,
                            'DAY' => ['MI', 'VI'],
                            'HOUR' => ['14:15-15:45', '08:15-09:45'],
                            'CLASS' => ['617', '623'],
                        ],
                        '6' => [
                            'PROFESSOR' => 'POR DESIGNAR DOCENTE',
                            'AUX_INDEX' => -1,
                            'DAY' => ['LU'],
                            'HOUR' => ['08:15-09:45'],
                            'CLASS' => ['617'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->escribir('locations.json', [
            'fcyt' => [[
                'id' => 'blk_0_1',
                'nombre' => 'EDIFICIO ACADEMICO',
                'polygon' => [[-66.14435, -17.39445], [-66.14425, -17.39445], [-66.14425, -17.39455], [-66.14435, -17.39445]],
                'aulas' => ['617'],
                'pisos' => [],
            ]],
        ]);

        $this->escribir('pensum.json', []);

        $this->escribir('fuentes.json', [
            'calendarios_academicos' => [[
                'facultad' => 'FCYT',
                'periodo' => '2/2026',
                'fechas_leidas' => ['inicio' => '2026-08-10', 'fin' => '2026-12-26'],
            ]],
        ]);
    }

    /**
     * @param  array<mixed>  $contenido
     */
    private function escribir(string $archivo, array $contenido): void
    {
        file_put_contents(
            $this->carpeta.'/'.$archivo,
            json_encode($contenido, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
    }
}
